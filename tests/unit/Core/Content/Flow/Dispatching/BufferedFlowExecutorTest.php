<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Driver\PDO\Exception as DbalPdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(BufferedFlowExecutor::class)]
class BufferedFlowExecutorTest extends TestCase
{
    private BufferedFlowExecutor $bufferedFlowExecutor;

    private MockObject&FlowLoader $flowLoaderMock;

    private MockObject&FlowFactory $flowFactoryMock;

    private MockObject&FlowExecutor $flowExecutorMock;

    private MockObject&LoggerInterface $loggerMock;

    protected function setUp(): void
    {
        $this->flowLoaderMock = $this->createMock(FlowLoader::class);
        $this->flowFactoryMock = $this->createMock(FlowFactory::class);
        $this->flowExecutorMock = $this->createMock(FlowExecutor::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->bufferedFlowExecutor = new BufferedFlowExecutor(
            $this->flowLoaderMock,
            $this->flowFactoryMock,
            $this->flowExecutorMock,
            $this->loggerMock,
        );
    }

    public function testDoesNotRegisterEvents(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        static::assertEmpty($this->bufferedFlowExecutor::getSubscribedEvents());
    }

    public function testExecutesBufferedEvents(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactoryMock->method('create')->willReturn($flow);

        $flowPayload = new Flow(Uuid::randomHex());
        $this->flowLoaderMock->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => $flowPayload,
                ],
            ],
        ]);

        $this->flowExecutorMock->expects(static::once())
            ->method('execute')
            ->with($flowPayload, $flow);

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    public function testExecuteBufferedEventsWithoutFlows(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('name', $event->getContext(), [], []);
        $this->flowFactoryMock->expects(static::once())
            ->method('create')
            ->willReturn($flow);
        $this->flowLoaderMock->expects(static::once())
            ->method('load')
            ->willReturn([]);

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    public function testLogsErrorIfMaximumExecutionDepthIsExceeded(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactoryMock->method('create')->willReturn($flow);

        $flowPayload = new Flow(Uuid::randomHex());
        $this->flowLoaderMock->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => $flowPayload,
                ],
            ],
        ]);
        $this->flowExecutorMock->method('execute')->willReturnCallback(function (): void {
            $this->bufferedFlowExecutor->bufferFlowExecution($this->createCheckoutOrderPlacedEvent(new OrderEntity()));
        });
        $this->loggerMock->expects(static::once())
            ->method('error')
            ->with(
                'Maximum execution depth reached for buffered flow executor. This might be caused by a cyclic flow execution.',
                ['bufferedEvents' => ['checkout.order.placed']],
            );

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    public function testSequenceExceptionsAreLogged(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactoryMock->method('create')->willReturn($flow);

        $this->flowLoaderMock->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
            ],
        ]);

        $internalException = FlowException::transactionFailed(new TableNotFoundException(
            new DbalPdoException('Table not found', null, 1146),
            null
        ));

        $this->flowExecutorMock->expects(static::once())
            ->method('execute')
            ->willThrowException(new ExecuteSequenceException(
                'flow-1',
                'sequence-1',
                $internalException->getMessage(),
                0,
                $internalException
            ));

        $this->loggerMock->expects(static::once())
            ->method('error')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    public function testGenericExceptionsAreLogged(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactoryMock->method('create')->willReturn($flow);

        $this->flowLoaderMock->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => new Flow(Uuid::randomHex()),
                ],
            ],
        ]);

        $this->flowExecutorMock->expects(static::once())
            ->method('execute')
            ->willThrowException(new \Exception('Something went wrong'));

        $this->loggerMock->expects(static::once())
            ->method('error')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSomething went wrong\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof \Exception;
                })
            );

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    private function createCheckoutOrderPlacedEvent(OrderEntity $order): CheckoutOrderPlacedEvent
    {
        $context = Generator::generateSalesChannelContext();

        return new CheckoutOrderPlacedEvent($context, $order);
    }
}

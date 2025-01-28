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
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(BufferedFlowExecutor::class)]
class BufferedFlowExecutorTest extends TestCase
{
    private BufferedFlowExecutor $bufferedFlowExecutor;

    private MockObject&LoggerInterface $loggerMock;

    private MockObject&FlowFactory $flowFactoryMock;

    private MockObject&ContainerInterface $containerMock;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->flowFactoryMock = $this->createMock(FlowFactory::class);

        $this->bufferedFlowExecutor = new BufferedFlowExecutor(
            $this->containerMock,
        );
    }

    public function testDoesNotRegisterEvents(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $executor = new BufferedFlowExecutor(
            $this->createMock(ContainerInterface::class),
        );

        static::assertEmpty($executor::getSubscribedEvents());
    }

    public function testDoesNotLoadServicesIfNoEventsAreBuffered(): void
    {
        $this->containerMock->expects(static::never())
            ->method('get');

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    public function testExecutesBufferedEvents(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactoryMock->method('create')->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowPayload = new Flow(Uuid::randomHex());
        $flowLoader->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => $flowPayload,
                ],
            ],
        ]);

        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->expects(static::once())
            ->method('execute');

        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $flowLoader,
            $this->flowFactoryMock,
            $flowExecutor,
        );

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

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowLoader->expects(static::once())
            ->method('load')
            ->willReturn([]);

        $this->containerMock->expects(static::exactly(2))
            ->method('get')
            ->willReturnOnConsecutiveCalls(
                $flowLoader,
                $this->flowFactoryMock
            );

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    public function testLogsErrorIfMaximumExecutionDepthIsExceeded(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactoryMock->method('create')->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowPayload = new Flow(Uuid::randomHex());
        $flowLoader->method('load')->willReturn([
            'state_enter.order.state.in_progress' => [
                [
                    'id' => 'flow-1',
                    'name' => 'Order enters status in progress',
                    'payload' => $flowPayload,
                ],
            ],
        ]);
        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->method('execute')->willReturnCallback(function (): void {
            $this->bufferedFlowExecutor->bufferFlowExecution($this->createCheckoutOrderPlacedEvent(new OrderEntity()));
        });
        $this->containerMock->method('get')->willReturnCallback(function (string $service) use ($flowLoader, $flowExecutor) {
            return match ($service) {
                FlowLoader::class => $flowLoader,
                FlowFactory::class => $this->flowFactoryMock,
                FlowExecutor::class => $flowExecutor,
                'logger' => $this->loggerMock,
                default => null,
            };
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

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowLoader->method('load')->willReturn([
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

        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->expects(static::once())
            ->method('execute')
            ->willThrowException(new ExecuteSequenceException(
                'flow-1',
                'sequence-1',
                $internalException->getMessage(),
                0,
                $internalException
            ));

        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $flowLoader,
            $this->flowFactoryMock,
            $flowExecutor,
            $this->loggerMock,
        );

        $this->loggerMock->expects(static::once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );

        $this->bufferedFlowExecutor->executeBufferedEvents();
    }

    public function testExceptionsAreLoggedAndExecutionContinuesWhenNestedTransactionsWithSavePointsIsEnabled(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $this->bufferedFlowExecutor->bufferFlowExecution($event);

        $flow = new StorableFlow('state_enter.order.state.in_progress', $event->getContext(), [], []);
        $this->flowFactoryMock->method('create')->willReturn($flow);

        $flowLoader = $this->createMock(FlowLoader::class);
        $flowLoader->method('load')->willReturn([
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
        $flowExecutor = $this->createMock(FlowExecutor::class);
        $flowExecutor->expects(static::once())
            ->method('execute')
            ->willThrowException(new ExecuteSequenceException(
                'flow-1',
                'sequence-1',
                $internalException->getMessage(),
                0,
                $internalException
            ));

        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $flowLoader,
            $this->flowFactoryMock,
            $flowExecutor,
            $this->loggerMock,
        );

        $this->loggerMock->expects(static::once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
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

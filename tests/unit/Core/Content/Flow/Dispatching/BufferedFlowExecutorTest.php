<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Flow\Dispatching;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Driver\PDO\Exception as DbalPdoException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Flow\Dispatching\BufferedFlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\BufferFlowExecutionEvent;
use Shopware\Core\Content\Flow\Dispatching\FlowExecutor;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\Dispatching\FlowLoader;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Content\Flow\Dispatching\Struct\Flow;
use Shopware\Core\Content\Flow\Exception\ExecuteSequenceException;
use Shopware\Core\Content\Flow\FlowException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
    private BufferedFlowExecutor $flowExecutor;

    private MockObject&Connection $connectionMock;

    private MockObject&LoggerInterface $loggerMock;

    private MockObject&FlowFactory $flowFactoryMock;

    private MockObject&EntityRepository $flowExecutionRepositoryMock;

    private MockObject&ContainerInterface $containerMock;

    protected function setUp(): void
    {
        $this->containerMock = $this->createMock(ContainerInterface::class);
        $this->connectionMock = $this->createMock(Connection::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->flowFactoryMock = $this->createMock(FlowFactory::class);
        $this->flowExecutionRepositoryMock = $this->createMock(EntityRepository::class);

        $this->flowExecutor = new BufferedFlowExecutor(
            $this->containerMock,
            $this->flowExecutionRepositoryMock
        );
    }

    public function testDoesNotRegisterEvents(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $executor = new BufferedFlowExecutor(
            $this->createMock(ContainerInterface::class),
            $this->createMock(EntityRepository::class)
        );

        static::assertEmpty($executor::getSubscribedEvents());
    }

    public function testExecutesBufferedEvents(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $bufferedFlowExecutionEvent = new BufferFlowExecutionEvent($event);

        $this->flowExecutor->handleBufferFlowExecutionEvent($bufferedFlowExecutionEvent);

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

        $this->connectionMock->method('getTransactionNestingLevel')->willReturn(1);

        $this->flowExecutionRepositoryMock->expects(static::once())
            ->method('create')
            ->with(
                [
                    [
                        'flowId' => 'flow-1',
                        'eventData' => $flow->stored(),
                        'successful' => true,
                    ],
                ],
                $event->getContext(),
            );

        $this->flowExecutor->executeBufferedEvents();
    }

    public function testExecuteBufferedEventsWithoutFlows(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $bufferedFlowExecutionEvent = new BufferFlowExecutionEvent($event);

        $this->flowExecutor->handleBufferFlowExecutionEvent($bufferedFlowExecutionEvent);

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

        $this->flowExecutor->executeBufferedEvents();
    }

    public function testSequenceExceptionsAreLogged(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $bufferedFlowExecutionEvent = new BufferFlowExecutionEvent($event);

        $this->flowExecutor->handleBufferFlowExecutionEvent($bufferedFlowExecutionEvent);

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

        $this->connectionMock->method('getTransactionNestingLevel')->willReturnOnConsecutiveCalls(1);
        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $flowLoader,
            $this->flowFactoryMock,
            $flowExecutor,
            $this->loggerMock,
            $this->connectionMock,
            $this->connectionMock,
        );

        $this->loggerMock->expects(static::once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );

        $this->flowExecutionRepositoryMock->expects(static::once())
            ->method('create')
            ->with(
                [
                    [
                        'flowId' => 'flow-1',
                        'eventData' => $flow->stored(),
                        'successful' => false,
                        'errorMessage' => 'Flow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found',
                        'failedFlowSequenceId' => 'sequence-1',
                    ],
                ],
                $event->getContext(),
            );

        $this->flowExecutor->executeBufferedEvents();
    }

    public function testGenericExceptionsAreLogged(): void
    {
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $bufferedFlowExecutionEvent = new BufferFlowExecutionEvent($event);

        $this->flowExecutor->handleBufferFlowExecutionEvent($bufferedFlowExecutionEvent);

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
            ->willThrowException($internalException);

        $this->containerMock->method('get')->willReturnOnConsecutiveCalls(
            $flowLoader,
            $this->flowFactoryMock,
            $flowExecutor,
            $this->loggerMock,
            $this->connectionMock,
            $this->connectionMock,
        );

        $this->connectionMock->method('getTransactionNestingLevel')->willReturnOnConsecutiveCalls(1);

        $this->loggerMock->expects(static::once())
            ->method('error')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof FlowException;
                })
            );
        $this->flowExecutionRepositoryMock->expects(static::once())
            ->method('create')
            ->with(
                [
                    [
                        'flowId' => 'flow-1',
                        'eventData' => $flow->stored(),
                        'successful' => false,
                        'errorMessage' => 'Flow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found',
                    ],
                ],
                $event->getContext(),
            );

        $this->flowExecutor->executeBufferedEvents();
    }

    public function testExceptionsAreLoggedAndExecutionContinuesWhenNestedTransactionsWithSavePointsIsEnabled(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);
        $event = $this->createCheckoutOrderPlacedEvent(new OrderEntity());

        $bufferedFlowExecutionEvent = new BufferFlowExecutionEvent($event);

        $this->flowExecutor->handleBufferFlowExecutionEvent($bufferedFlowExecutionEvent);

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
            $this->connectionMock,
            $this->connectionMock,
        );

        $this->connectionMock->method('getTransactionNestingLevel')->willReturn(1);
        $this->connectionMock->method('getNestTransactionsWithSavepoints')->willReturn(true);

        $this->loggerMock->expects(static::once())
            ->method('warning')
            ->with(
                "Could not execute flow with error message:\nFlow name: Order enters status in progress\nFlow id: flow-1\nSequence id: sequence-1\nFlow action transaction could not be committed and was rolled back. Exception: An exception occurred in the driver: Table not found\nError Code: 0\n",
                static::callback(static function (array $context) {
                    return $context['exception'] instanceof ExecuteSequenceException;
                })
            );
        $this->flowExecutionRepositoryMock->expects(static::once())
            ->method('create')
            ->with(
                [
                    [
                        'flowId' => 'flow-1',
                        'eventData' => $flow->stored(),
                        'successful' => false,
                        'errorMessage' => 'Transaction level was not 0 after flow execution',
                        'failedFlowSequenceId' => 'sequence-1',
                    ],
                ],
                $event->getContext(),
            );

        $this->flowExecutor->executeBufferedEvents();
    }

    private function createCheckoutOrderPlacedEvent(OrderEntity $order): CheckoutOrderPlacedEvent
    {
        $context = Generator::createSalesChannelContext();

        return new CheckoutOrderPlacedEvent($context, $order);
    }
}

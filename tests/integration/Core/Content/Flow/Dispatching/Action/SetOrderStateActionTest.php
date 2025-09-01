<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow\Dispatching\Action;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Content\Flow\Dispatching\Action\SetOrderStateAction;
use Shopware\Core\Content\Flow\Dispatching\FlowFactory;
use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Content\Test\Flow\OrderActionTrait;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('after-sales')]
class SetOrderStateActionTest extends TestCase
{
    use OrderActionTrait;

    /**
     * @var EntityRepository<OrderCollection>
     */
    private EntityRepository $orderRepository;

    /**
     * @var EntityRepository<OrderDeliveryCollection>
     */
    private EntityRepository $orderDeliveryRepository;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->flowRepository = static::getContainer()->get('flow.repository');

        $this->connection = static::getContainer()->get(Connection::class);

        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->orderDeliveryRepository = static::getContainer()->get('order_delivery.repository');

        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomSalesChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
        ]);

        $shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
        $shippingMethodRepository->create([
            [
                'id' => $this->ids->get('shipping-method'),
                'name' => 'test',
                'technicalName' => 'test',
                'active' => true,
                'deliveryTimeId' => static::getContainer()->get('delivery_time.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId(),
                'prices' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'calculation' => 1,
                        'quantityStart' => 1,
                        'quantityEnd' => 100,
                        'currencyPrice' => [
                            [
                                'gross' => 0,
                                'net' => 0,
                                'linked' => false,
                                'currencyId' => Defaults::CURRENCY,
                            ],
                        ],
                    ],
                ],
                'salesChannels' => [
                    ['id' => $this->ids->get('sales-channel')],
                ],
                'salesChannelDefaultAssignments' => [
                    ['id' => $this->ids->get('sales-channel')],
                ],
            ],
        ], Context::createDefaultContext());

        $this->orderRepository = static::getContainer()->get('order.repository');

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->ids->create('token'));
    }

    public function testSetAvailableOrderState(): void
    {
        $orderState = 'cancelled';
        $orderDeliveryState = 'cancelled';
        $orderTransactionState = 'cancelled';
        $this->prepareFlowSequences($orderState, $orderDeliveryState, $orderTransactionState);
        $this->prepareProductTest();
        $this->createCustomerAndLogin();
        $this->submitOrder();

        $orderId = $this->getOrderId();
        $orderStateAfterAction = $this->getOrderState($orderId);
        static::assertSame($orderState, $orderStateAfterAction);

        $orderDeliveryStateAfterAction = $this->getOrderDeliveryState($orderId);
        static::assertSame($orderDeliveryState, $orderDeliveryStateAfterAction);

        $orderTransactionStateAfterAction = $this->getOrderTransactionState($orderId);
        static::assertSame($orderTransactionState, $orderTransactionStateAfterAction);
    }

    public function testSetAvailableOrderStateWithNotAvailableState(): void
    {
        $orderState = 'done';
        $orderDeliveryState = 'cancelled';
        $orderTransactionState = 'cancelled';
        $this->prepareFlowSequences($orderState, $orderDeliveryState, $orderTransactionState);
        $this->prepareProductTest();
        $this->createCustomerAndLogin();
        $this->submitOrder();

        $orderId = $this->getOrderId();
        $orderStateAfterAction = $this->getOrderState($orderId);
        static::assertNotSame($orderState, $orderStateAfterAction);

        $orderDeliveryStateAfterAction = $this->getOrderDeliveryState($orderId);
        static::assertNotSame($orderDeliveryState, $orderDeliveryStateAfterAction);

        $orderTransactionStateAfterAction = $this->getOrderTransactionState($orderId);
        static::assertNotSame($orderTransactionState, $orderTransactionStateAfterAction);
    }

    public function testSetStateOnOrderDeliveryWithDiscountOnDelivery(): void
    {
        $flowCreatePayload = [
            'id' => Uuid::randomHex(),
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'name' => 'Test Flow',
            'active' => true,
            'sequences' => [
                [
                    'id' => Uuid::randomHex(),
                    'actionName' => SetOrderStateAction::getName(),
                    'config' => [
                        'order_delivery' => 'shipped',
                        'force_transition' => false,
                    ],
                ],
            ],
        ];

        $this->flowRepository->create([$flowCreatePayload], Context::createDefaultContext());
        $this->createCustomerAndLogin();

        $customerId = $this->ids->get('customer');
        $orderId = $this->ids->get('order');
        $IdForShippingCosts = Uuid::randomHex();
        $orderDeliveries = [
            $this->generateOrderDeliveryPayload([
                'id' => $IdForShippingCosts,
                'orderId' => $orderId,
                'shippingCosts' => new CalculatedPrice(
                    10.00,
                    10.00,
                    new CalculatedTaxCollection([]),
                    new TaxRuleCollection([]),
                ),
            ]),
            $this->generateOrderDeliveryPayload([
                'orderId' => $orderId,
                'shippingCosts' => new CalculatedPrice(
                    -10.00,
                    -10.00,
                    new CalculatedTaxCollection([]),
                    new TaxRuleCollection([]),
                ),
            ]),
        ];

        $context = Generator::generateSalesChannelContext();

        $this->createOrder($customerId, ['deliveries' => $orderDeliveries, 'id' => $orderId]);
        $order = $this->orderRepository->search(new Criteria([$orderId]), $context->getContext())->first();

        static::assertInstanceOf(OrderEntity::class, $order);

        $event = new CheckoutOrderPlacedEvent($context, $order);

        $subscriber = new SetOrderStateAction(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(OrderService::class),
        );

        /** @var FlowFactory $flowFactory */
        $flowFactory = static::getContainer()->get(FlowFactory::class);
        $flow = $flowFactory->create($event);
        $flow->setConfig(['order_delivery' => 'shipped']);

        $subscriber->handleFlow($flow);

        $criteria = new Criteria([$IdForShippingCosts]);
        $criteria->addAssociation('stateMachineState');
        $oderDeliveryForShippingCosts = $this->orderDeliveryRepository->search($criteria, $context->getContext())->first();

        static::assertInstanceOf(OrderDeliveryEntity::class, $oderDeliveryForShippingCosts);
        static::assertSame('shipped', $oderDeliveryForShippingCosts->getStateMachineState()?->getTechnicalName());
    }

    /**
     * @param array<int|string, mixed> $payload
     *
     * @return array<int|string, mixed>
     */
    public function generateOrderDeliveryPayload(array $payload = []): array
    {
        $payload = array_merge(
            [
                'id' => $this->ids->create('delivery'),
                'stateId' => static::getContainer()->get(InitialStateIdLoader::class)->get(OrderDeliveryStates::STATE_MACHINE),
                'shippingMethodId' => $this->getValidShippingMethodId(),
                'shippingCosts' => new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection()),
                'shippingDateEarliest' => date(\DATE_ATOM),
                'shippingDateLatest' => date(\DATE_ATOM),
                'shippingOrderAddressId' => $this->ids->get('shipping-address'),
                'trackingCodes' => [],
                'positions' => [
                    [
                        'id' => $this->ids->create('position'),
                        'orderLineItemId' => $this->ids->create('line-item'),
                        'price' => new CalculatedPrice(200, 200, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ],
                ],
            ],
            $payload,
        );

        return $payload;
    }

    private function prepareFlowSequences(string $orderState, string $orderDeliveryState, string $orderTransactionState): void
    {
        $flowSequences = [
            'name' => 'Create Order',
            'eventName' => CheckoutOrderPlacedEvent::EVENT_NAME,
            'priority' => 100,
            'active' => true,
            'sequences' => [
                [
                    'id' => Uuid::randomHex(),
                    'parentId' => null,
                    'ruleId' => null,
                    'actionName' => SetOrderStateAction::getName(),
                    'config' => [
                        'order' => $orderState,
                        'order_delivery' => $orderDeliveryState,
                        'order_transaction' => $orderTransactionState,
                    ],
                    'position' => 1,
                    'trueCase' => true,
                ],
            ],
        ];

        $this->flowRepository->create([$flowSequences], Context::createDefaultContext());
    }

    private function getOrderId(): string
    {
        return $this->connection->fetchOne(
            '
            SELECT id
            FROM `order`
            Order By `created_at` ASC
            '
        );
    }

    private function getOrderState(string $orderId): string
    {
        return $this->connection->fetchOne(
            '
            SELECT state_machine_state.technical_name
            FROM `order` od
            INNER JOIN state_machine_state ON od.state_id = state_machine_state.id
            WHERE od.id = :id
            ',
            ['id' => $orderId]
        );
    }

    private function getOrderDeliveryState(string $orderId): string
    {
        return $this->connection->fetchOne(
            '
            SELECT state_machine_state.technical_name
            FROM `order` od
            JOIN order_delivery ON order_delivery.order_id = od.id
            JOIN state_machine_state ON order_delivery.state_id = state_machine_state.id
            WHERE od.id = :id
            ',
            ['id' => $orderId]
        );
    }

    private function getOrderTransactionState(string $orderId): string
    {
        return $this->connection->fetchOne(
            '
            SELECT state_machine_state.technical_name
            FROM `order` od
            JOIN order_transaction ON order_transaction.order_id = od.id
            JOIN state_machine_state ON order_transaction.state_id = state_machine_state.id
            WHERE od.id = :id
            ',
            ['id' => $orderId]
        );
    }
}

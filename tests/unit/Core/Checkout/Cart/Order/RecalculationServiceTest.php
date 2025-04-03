<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConversionContext;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Order\Transformer\CartTransformer;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\RuleLoaderResult;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(RecalculationService::class)]
#[Package('checkout')]
class RecalculationServiceTest extends TestCase
{
    private SalesChannelContext $salesChannelContext;

    private OrderConverter&MockObject $orderConverter;

    private CartRuleLoader&MockObject $cartRuleLoader;

    private Context $context;

    protected function setUp(): void
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);
        $this->orderConverter = $this->createMock(OrderConverter::class);
        $this->orderConverter
            ->method('assembleSalesChannelContext')
            ->willReturnCallback(function (OrderEntity $order, Context $context) {
                static::assertNotNull($order->getTaxStatus());
                $context->setTaxState($order->getTaxStatus());

                $salesChannel = new SalesChannelEntity();
                $salesChannel->setId(Uuid::randomHex());

                return Generator::generateSalesChannelContext(
                    baseContext: $context,
                    salesChannel: $salesChannel
                );
            });

        $this->cartRuleLoader = $this->createMock(CartRuleLoader::class);
        $this->context = Context::createDefaultContext();
    }

    public function testRecalculateOrderWithTaxStatus(): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);

        $orderEntity = $this->orderEntity();
        $orderEntity->setDeliveries(new OrderDeliveryCollection([$this->orderDeliveryEntity()]));
        $cart = $this->getCart();
        $cart->add($lineItem);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$orderEntity]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data, Context $context) use ($orderEntity) {
                static::assertSame($data[0]['stateId'], $orderEntity->getStateId());
                static::assertNotNull($data[0]['deliveries']);
                static::assertNotNull($data[0]['deliveries'][0]);
                static::assertSame($data[0]['deliveries'][0]['stateId'], $orderEntity->getDeliveries()?->first()?->getStateId());

                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);

                $price = $data[0]['price'];
                self::assertInstanceOf(CartPrice::class, $price);

                static::assertSame($price->getTaxStatus(), CartPrice::TAX_STATE_FREE);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
                ]), []);
            });

        $this->orderConverter
            ->expects($this->once())
            ->method('convertToCart')
            ->willReturnCallback(function (OrderEntity $order, Context $context) use ($cart) {
                static::assertSame($order->getTaxStatus(), CartPrice::TAX_STATE_FREE);
                static::assertSame($context->getTaxState(), CartPrice::TAX_STATE_FREE);

                return $cart;
            });

        $this->orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                $salesChannelContext = $this->createMock(SalesChannelContext::class);
                $salesChannelContext->method('getTaxState')
                    ->willReturn(CartPrice::TAX_STATE_FREE);

                $order = CartTransformer::transform(
                    $cart,
                    $salesChannelContext,
                    '',
                    $conversionContext->shouldIncludeOrderDate()
                );

                // add empty delivery to trigger settings the state id
                if ($conversionContext->shouldIncludeDeliveries()) {
                    $order['deliveries'] = [[
                        'id' => Uuid::randomHex(),
                        'stateId' => 'some-random-state-id',
                        'shippingCosts' => new CalculatedPrice(0, 0, new CalculatedTaxCollection(), new TaxRuleCollection()),
                    ]];
                }

                return $order;
            });

        $this->cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->willReturn(
                new RuleLoaderResult(
                    $cart,
                    new RuleCollection()
                )
            );

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->recalculate($orderEntity->getId(), $this->context);
    }

    public function testAddProductToOrder(): void
    {
        $order = $this->orderEntity();
        $order->setDeliveries(new OrderDeliveryCollection([$this->orderDeliveryEntity()]));

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());
                static::assertFalse(isset($data[0]['deliveries']));

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());

        // We check product existence by searchIds
        /** @var StaticEntityRepository<ProductCollection> */
        $productRepository = new StaticEntityRepository([
            [$productEntity->getId()],
        ]);

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addProductToOrder($order->getId(), $productEntity->getId(), 1, $this->context);
    }

    public function testAddCustomLineItem(): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);

        $order = $this->orderEntity();
        $cart = $this->getCart();
        $cart->add($lineItem);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addCustomLineItem($order->getId(), $lineItem, $this->context);
    }

    public function testAssertProcessorsCalledWithLiveVersion(): void
    {
        $deliveryEntity = new OrderDeliveryEntity();
        $deliveryEntity->setId(Uuid::randomHex());
        $deliveryEntity->setStateId(Uuid::randomHex());

        $deliveries = new OrderDeliveryCollection([$deliveryEntity]);

        $order = $this->orderEntity();
        $order->setDeliveries($deliveries);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());
                static::assertFalse(isset($data[0]['deliveries']));

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $productEntity = new ProductEntity();
        $productEntity->setId(Uuid::randomHex());

        // We check product existence by searchIds
        /** @var StaticEntityRepository<ProductCollection> */
        $productRepository = new StaticEntityRepository([
            [$productEntity->getId()],
        ]);

        $processor = new LiveProcessorValidator();

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $productRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $processor,
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addProductToOrder($order->getId(), $productEntity->getId(), 1, $this->context);

        static::assertSame(Defaults::LIVE_VERSION, $processor->versionId);
    }

    public function testAddPromotionLineItem(): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE);

        $order = $this->orderEntity();
        $cart = $this->getCart();
        $cart->add($lineItem);

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) use ($order) {
                static::assertSame($data[0]['stateId'], $order->getStateId());

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], $this->context),
                ]), []);
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addPromotionLineItem($order->getId(), '', $this->context);
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testToggleAutomaticPromotion(): void
    {
        $order = $this->orderEntity();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert');

        $this->orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->with(static::anything(), static::anything(), static::callback(static function (OrderConversionContext $context) {
                return $context->shouldIncludeDeliveries();
            }))
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                return CartTransformer::transform(
                    $cart,
                    $context,
                    '',
                    $conversionContext->shouldIncludeOrderDate()
                );
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->toggleAutomaticPromotion($order->getId(), $this->context, false);
    }

    public function testRecalculateOrderWithEmptyLineItems(): void
    {
        $orderEntity = $this->orderEntity();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$orderEntity]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $entityRepository
            ->expects($this->once())
            ->method('upsert')
            ->willReturnCallback(function (array $data) {
                static::assertNotNull($data[0]);
                static::assertEmpty($data[0]['deliveries']);

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
                    new EntityWrittenEvent('order', [new EntityWriteResult('created-id', [], 'order', EntityWriteResult::OPERATION_INSERT)], Context::createDefaultContext()),
                ]), []);
            });

        $this->orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->willReturnCallback(function (Cart $cart, SalesChannelContext $context, OrderConversionContext $conversionContext) {
                $salesChannelContext = $this->createMock(SalesChannelContext::class);
                $salesChannelContext->method('getTaxState')
                    ->willReturn(CartPrice::TAX_STATE_FREE);

                return CartTransformer::transform(
                    $cart,
                    $salesChannelContext,
                    '',
                    $conversionContext->shouldIncludeOrderDate()
                );
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $this->createMock(Processor::class),
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->recalculate($orderEntity->getId(), $this->context);
    }

    public function testSetCartErrorToValidatedCart(): void
    {
        $order = $this->orderEntity();

        $entityRepository = $this->createMock(EntityRepository::class);
        $entityRepository->method('search')->willReturnOnConsecutiveCalls(
            new EntitySearchResult('order', 1, new OrderCollection([$order]), null, new Criteria(), $this->salesChannelContext->getContext()),
        );

        $persistentError = $this->createMock(Error::class);
        $persistentError
            ->expects($this->once())
            ->method('isPersistent')
            ->willReturn(true);

        $nonPersistentError = $this->createMock(Error::class);
        $nonPersistentError
            ->expects($this->once())
            ->method('isPersistent')
            ->willReturn(false);

        $cart = new Cart('some-token');
        $cart->setErrors(new ErrorCollection([$persistentError, $nonPersistentError]));

        $processorMock = $this->createMock(Processor::class);
        $processorMock
            ->expects($this->once())
            ->method('process')
            ->willReturn($cart);

        $this->cartRuleLoader
            ->expects($this->once())
            ->method('loadByCart')
            ->willReturn(new RuleLoaderResult(new Cart('reloaded-cart'), new RuleCollection()));

        $this->orderConverter
            ->expects($this->once())
            ->method('convertToOrder')
            ->willReturnCallback(static function (Cart $validatedCart) {
                static::assertCount(1, $validatedCart->getErrors());
                static::assertInstanceOf(Error::class, $validatedCart->getErrors()->first());

                return [];
            });

        $recalculationService = new RecalculationService(
            $entityRepository,
            $this->orderConverter,
            $this->createMock(CartService::class),
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $entityRepository,
            $processorMock,
            $this->cartRuleLoader,
            $this->createMock(PromotionItemBuilder::class)
        );

        $recalculationService->addCustomLineItem($order->getId(), new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE), $this->context);
    }

    private function orderEntity(): OrderEntity
    {
        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $order->setSalesChannelId(Uuid::randomHex());
        $order->setTaxStatus(CartPrice::TAX_STATE_FREE);
        $order->setStateId(Uuid::randomHex());

        return $order;
    }

    private function orderDeliveryEntity(int $price = 0): OrderDeliveryEntity
    {
        $delivery = new OrderDeliveryEntity();
        $delivery->setId(Uuid::randomHex());
        $delivery->setStateId(Uuid::randomHex());
        $delivery->setShippingCosts(new CalculatedPrice(
            $price,
            $price,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
        ));

        return $delivery;
    }

    private function getCart(): Cart
    {
        $cart = new Cart(Uuid::randomHex());

        $cart->setPrice(new CartPrice(
            0.0,
            0.0,
            0.0,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_FREE
        ));

        return $cart;
    }
}

/**
 * @internal
 */
#[Package('checkout')]
class LiveProcessorValidator extends Processor
{
    public ?string $versionId = null;

    public function __construct()
    {
    }

    public function process(Cart $original, SalesChannelContext $context, CartBehavior $behavior): Cart
    {
        TestCase::assertSame(Defaults::LIVE_VERSION, $context->getVersionId());
        $this->versionId = $context->getVersionId();

        return $original;
    }
}

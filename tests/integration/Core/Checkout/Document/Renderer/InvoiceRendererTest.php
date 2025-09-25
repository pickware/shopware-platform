<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Document\Renderer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\ProductLineItemFactory;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\PriceDefinitionFactory;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Document\Event\DocumentTemplateRendererParameterEvent;
use Shopware\Core\Checkout\Document\Event\InvoiceOrdersEvent;
use Shopware\Core\Checkout\Document\Renderer\DocumentRendererConfig;
use Shopware\Core\Checkout\Document\Renderer\InvoiceRenderer;
use Shopware\Core\Checkout\Document\Renderer\OrderDocumentCriteriaFactory;
use Shopware\Core\Checkout\Document\Renderer\RenderedDocument;
use Shopware\Core\Checkout\Document\Service\HtmlRenderer;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\TaxFreeConfig;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyFormatter;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Tests\Integration\Core\Checkout\Document\DocumentTrait;

/**
 * @internal
 */
#[Package('after-sales')]
class InvoiceRendererTest extends TestCase
{
    use AppSystemTestBehaviour;
    use DocumentTrait;
    use SnapshotTesting;

    private SalesChannelContext $salesChannelContext;

    private Context $context;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private InvoiceRenderer $invoiceRenderer;

    private CartService $cartService;

    private static string $deLanguageId;

    private static ?\Closure $callback = null;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();

        $priceRuleId = Uuid::randomHex();

        $this->salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $this->createCustomer(),
            ]
        );

        $this->salesChannelContext->setRuleIds([$priceRuleId]);
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->invoiceRenderer = static::getContainer()->get(InvoiceRenderer::class);
        $this->cartService = static::getContainer()->get(CartService::class);
        self::$deLanguageId = $this->getDeDeLanguageId();
    }

    protected function tearDown(): void
    {
        static::getContainer()->get(Translator::class)->reset();

        if (self::$callback instanceof \Closure) {
            static::getContainer()->get('event_dispatcher')->removeListener(DocumentTemplateRendererParameterEvent::class, self::$callback);
        }

        parent::tearDown();
    }

    public function testDocumentSnapshot(): void
    {
        $translator = static::getContainer()->get(Translator::class);
        $translator->injectSettings(
            $this->salesChannelContext->getSalesChannelId(),
            $this->salesChannelContext->getLanguageId(),
            'en-GB',
            $this->salesChannelContext->getContext()
        );

        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        static::getContainer()->get('order.repository')->update([
            [
                'id' => $orderId,
                'orderDateTime' => '2023-11-24T12:00:00+00:00',
            ],
        ], $this->context);

        $operation = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION, [
            'itemsPerPage' => 10,
            'displayHeader' => true,
            'displayFooter' => true,
            'displayPrices' => true,
            'displayPageCount' => true,
            'displayLineItems' => true,
            'displayCompanyAddress' => true,
            'displayReturnAddress' => true,
            'companyName' => 'Example Company',
            'documentDate' => '2023-11-24T12:00:00+00:00',
        ]);

        $processedTemplate = $this->invoiceRenderer->render(
            [$orderId => $operation],
            $this->context,
            new DocumentRendererConfig()
        );

        $rendered = $processedTemplate->getSuccess()[$orderId];
        static::assertInstanceOf(RenderedDocument::class, $rendered);

        $content = $rendered->getContent();

        // replace the date in the meta tag to avoid snapshot differences
        $processedHtml = preg_replace(
            '/(<meta name="date" content=")(.*?)(")/i',
            '$1[date]$3',
            $content
        );
        static::assertIsString($processedHtml);

        $this->assertHtmlSnapshot(
            'invoice_renderer_default',
            $processedHtml
        );
    }

    /**
     * @param array<int|string, int> $possibleTaxes
     */
    #[DataProvider('invoiceDataProvider')]
    public function testRender(array $possibleTaxes, ?\Closure $beforeRenderHook, \Closure $assertionCallback): void
    {
        $cart = $this->generateDemoCart($possibleTaxes);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION);

        $caughtEvent = null;

        static::getContainer()->get('event_dispatcher')
            ->addListener(InvoiceOrdersEvent::class, function (InvoiceOrdersEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            });

        if ($beforeRenderHook instanceof \Closure) {
            $beforeRenderHook($operationInvoice, static::getContainer());
        }

        $processedTemplate = $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertInstanceOf(InvoiceOrdersEvent::class, $caughtEvent);
        static::assertCount(1, $caughtEvent->getOperations());
        static::assertSame($operationInvoice, $caughtEvent->getOperations()[$orderId] ?? null);
        static::assertCount(1, $caughtEvent->getOrders());
        $order = $caughtEvent->getOrders()->get($orderId);
        static::assertNotNull($order);

        if ($processedTemplate->getSuccess() !== []) {
            static::assertArrayHasKey($orderId, $processedTemplate->getSuccess());

            /** @var RenderedDocument $rendered */
            $rendered = $processedTemplate->getSuccess()[$orderId];

            static::assertInstanceOf(OrderLineItemCollection::class, $lineItems = $order->getLineItems());
            static::assertInstanceOf(OrderLineItemEntity::class, $firstLineItem = $lineItems->first());
            static::assertInstanceOf(OrderLineItemEntity::class, $lastLineItem = $lineItems->last());
            static::assertStringContainsString($firstLineItem->getLabel(), $rendered->getContent());
            static::assertStringContainsString($lastLineItem->getLabel(), $rendered->getContent());

            $assertionCallback($rendered, $order, static::getContainer());
        } else {
            $assertionCallback($order->getId(), $processedTemplate->getErrors());
        }
    }

    public static function invoiceDataProvider(): \Generator
    {
        $documentDate = (new \DateTime());

        yield 'render with default language' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container) use ($documentDate): void {
                $operation->assign([
                    'config' => [
                        'displayHeader' => true,
                        'documentDate' => $documentDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'displayLineItems' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order, ContainerInterface $container) use ($documentDate): void {
                static::assertNotNull($order->getCurrency());

                static::assertStringContainsString(
                    $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        Context::createDefaultContext()->getLanguageId(),
                        Context::createDefaultContext(),
                    ),
                    $rendered->getContent()
                );

                static::assertNotNull($order->getLanguage());
                static::assertNotNull($locale = $order->getLanguage()->getLocale());
                $formatter = new \IntlDateFormatter($locale->getCode(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
                $formattedDate = $formatter->format($documentDate);

                static::assertNotFalse($formattedDate);
                static::assertStringContainsString(
                    \sprintf('Date %s', $formattedDate),
                    $rendered->getContent()
                );

                static::assertStringContainsString('<html lang="en-GB">', $rendered->getContent());
                static::assertStringContainsString('</html>', $rendered->getContent());
            },
        ];

        yield 'render with different language' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container) use ($documentDate): void {
                $container->get('order.repository')->upsert([[
                    'id' => $operation->getOrderId(),
                    'languageId' => self::$deLanguageId,
                ]], Context::createDefaultContext());

                $criteria = OrderDocumentCriteriaFactory::create([$operation->getOrderId()]);
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->get($operation->getOrderId());
                static::assertInstanceOf(OrderEntity::class, $order);

                $context = clone Context::createDefaultContext();
                $context = $context->assign([
                    'languageIdChain' => array_unique(array_filter([self::$deLanguageId, Context::createDefaultContext()->getLanguageId()])),
                ]);
                static::assertNotNull($order->getDeliveries());
                /** @var $delivery OrderDeliveryEntity */
                static::assertNotNull($delivery = $order->getDeliveries()->first());
                /** @var $shippingMethod ShippingMethodEntity */
                static::assertNotNull($shippingMethod = $delivery->getShippingMethod());

                $container->get('shipping_method.repository')->upsert([[
                    'id' => $shippingMethod->getId(),
                    'name' => 'DE express',
                ]], $context);

                $operation->assign([
                    'config' => [
                        'displayHeader' => true,
                        'documentDate' => $documentDate->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                        'displayLineItems' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order, ContainerInterface $container) use ($documentDate): void {
                static::assertNotNull($order->getCurrency());

                static::assertStringContainsString(
                    preg_replace('/\xc2\xa0/', ' ', $container->get(CurrencyFormatter::class)->formatCurrencyByLanguage(
                        $order->getAmountTotal(),
                        $order->getCurrency()->getIsoCode(),
                        self::$deLanguageId,
                        Context::createDefaultContext(),
                    )) ?? '',
                    preg_replace('/\xc2\xa0/', ' ', $rendered->getContent()) ?? ''
                );
                static::assertStringContainsString('DE express', preg_replace('/\xc2\xa0/', ' ', $rendered->getContent()) ?? 'DE express');

                static::assertNotNull($order->getLanguage());
                static::assertNotNull($locale = $order->getLanguage()->getLocale());
                $formatter = new \IntlDateFormatter($locale->getCode(), \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
                $formattedDate = $formatter->format($documentDate);

                static::assertNotFalse($formattedDate);
                static::assertStringContainsString(
                    \sprintf('Datum %s', $formattedDate),
                    $rendered->getContent()
                );

                static::assertStringContainsString('<html lang="de-DE">', $rendered->getContent());
                static::assertStringContainsString('</html>', $rendered->getContent());
            },
        ];

        yield 'render with syntax error' => [
            [7, 19, 22],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                self::$callback = function (DocumentTemplateRendererParameterEvent $event): void {
                    throw new \RuntimeException('Errors happened while rendering');
                };

                $container->get('event_dispatcher')->addListener(DocumentTemplateRendererParameterEvent::class, self::$callback);
            },
            function (string $orderId, array $errors): void {
                static::assertNotNull(self::$callback);
                static::assertNotEmpty($errors);
                static::assertArrayHasKey($orderId, $errors);

                /** @var \RuntimeException $error */
                $error = $errors[$orderId];
                static::assertSame(
                    'Errors happened while rendering',
                    $error->getMessage()
                );
            },
        ];

        yield 'render with different taxes' => [
            [7, 19, 22],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered): void {
                foreach ([7, 19, 22] as $possibleTax) {
                    static::assertStringContainsString(
                        \sprintf('plus %d%% VAT', $possibleTax),
                        $rendered->getContent()
                    );
                }
            },
        ];

        yield 'render with shipping address' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);
                $order = $container->get('order.repository')->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);
                static::assertNotNull($order->getDeliveries());
                /** @var CountryEntity $country */
                $country = $order->getDeliveries()->getShippingAddress()->getCountries()->first();
                $country->setCompanyTax(new TaxFreeConfig(true, Defaults::CURRENCY, 0));

                $container->get('country.repository')->update([[
                    'id' => $country->getId(),
                    'companyTax' => ['enabled' => true, 'currencyId' => Defaults::CURRENCY, 'amount' => 0],
                ]], Context::createDefaultContext());
                $companyPhone = '123123123';
                $vatIds = ['VAT-123123'];

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => $vatIds,
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'itemsPerPage' => 10,
                        'displayFooter' => true,
                        'displayHeader' => true,
                        'executiveDirector' => 'Max Mustermann',
                        'displayDivergentDeliveryAddress' => true,
                        'companyPhone' => $companyPhone,
                        'displayAdditionalNoteDelivery' => true,
                        'deliveryCountries' => [$country->getId()],
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($orderDeliveries = $order->getDeliveries());
                $shippingAddress = $orderDeliveries->getShippingAddress()->first();
                static::assertNotNull($shippingAddress);

                $rendered = $rendered->getContent();

                static::assertNotNull($shippingAddress->getZipcode());

                static::assertStringContainsString('Shipping address', $rendered);
                static::assertStringContainsString($shippingAddress->getStreet(), $rendered);
                static::assertStringContainsString($shippingAddress->getCity(), $rendered);
                static::assertStringContainsString($shippingAddress->getFirstName(), $rendered);
                static::assertStringContainsString($shippingAddress->getLastName(), $rendered);
                static::assertStringContainsString($shippingAddress->getZipcode(), $rendered);
                static::assertStringContainsString('123123123', $rendered);
            },
        ];

        yield 'render with billing address' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayFooter' => true,
                        'displayHeader' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());

                /** @var OrderAddressEntity $orderAddress */
                $orderAddress = $order->getAddresses()->first();
                $rendered = $rendered->getContent();

                static::assertNotNull($orderAddress->getSalutation());
                static::assertNotNull($orderAddress->getCountry());
                static::assertNotNull($orderAddress->getCountry()->getName());
                static::assertNotNull($orderAddress->getSalutation()->getLetterName());
                static::assertNotNull($orderAddress->getSalutation()->getDisplayName());
                static::assertNotNull($orderAddress->getZipcode());

                static::assertStringContainsString($orderAddress->getStreet(), $rendered);
                static::assertStringContainsString($orderAddress->getZipcode(), $rendered);
                static::assertStringContainsString($orderAddress->getCity(), $rendered);
                static::assertStringContainsString($orderAddress->getCountry()->getName(), $rendered);
            },
        ];

        yield 'render customer VAT-ID with displayCustomerVatId is checked' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

                static::assertNotNull($orderAddress);
                static::assertNotNull($orderAddress->getCountry());

                $container->get('country.repository')->upsert([[
                    'id' => $orderAddress->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayHeader' => true,
                        'displayCustomerVatId' => true,
                        'displayAdditionalNoteDelivery' => false,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();
                $rendered = $rendered->getContent();

                static::assertNotNull($customer);
                static::assertNotNull($customer->getVatIds());

                $vatId = $customer->getVatIds()[0];

                static::assertStringContainsString("VAT Reg.No: $vatId", $rendered);
            },
        ];

        yield 'render customer VAT-ID with displayCustomerVatId unchecked' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => ['VAT-123123'],
                ]], Context::createDefaultContext());

                $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

                static::assertNotNull($orderAddress);
                static::assertNotNull($orderAddress->getCountry());

                $container->get('country.repository')->upsert([[
                    'id' => $orderAddress->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayHeader' => true,
                        'displayFooter' => false,
                        'displayCustomerVatId' => false,
                        'displayAdditionalNoteDelivery' => false,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();
                $rendered = $rendered->getContent();

                static::assertNotNull($customer);
                static::assertNotNull($customer->getVatIds());

                static::assertStringNotContainsString('VAT Reg.No:', $rendered);
            },
        ];

        yield 'render with customer VAT-ID is null' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $orderId = $operation->getOrderId();
                $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

                $order = $container->get('order.repository')
                    ->search($criteria, Context::createDefaultContext())->get($orderId);
                static::assertInstanceOf(OrderEntity::class, $order);

                static::assertNotNull($order->getOrderCustomer());
                $container->get('customer.repository')->update([[
                    'id' => $order->getOrderCustomer()->getCustomerId(),
                    'vatIds' => [],
                ]], Context::createDefaultContext());

                $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

                static::assertNotNull($orderAddress);
                static::assertNotNull($orderAddress->getCountry());

                $container->get('country.repository')->upsert([[
                    'id' => $orderAddress->getCountry()->getId(),
                    'companyTax' => ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()],
                ]], Context::createDefaultContext());

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                        'displayFooter' => false,
                        'displayHeader' => true,
                        'displayCustomerVatId' => true,
                        'displayAdditionalNoteDelivery' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered, OrderEntity $order): void {
                static::assertNotNull($order->getAddresses());
                static::assertNotNull($order->getOrderCustomer());

                /** @var CustomerEntity $customer */
                $customer = $order->getOrderCustomer()->getCustomer();
                $rendered = $rendered->getContent();

                static::assertNotNull($customer);
                static::assertEmpty($customer->getVatIds());

                static::assertStringNotContainsString('VAT Reg.No:', $rendered);
            },
        ];

        yield 'render with credit item' => [
            [7],
            function (DocumentGenerateOperation $operation, ContainerInterface $container): void {
                $context = Context::createDefaultContext();
                $orderId = $operation->getOrderId();

                $versionId = $container->get('order.repository')->createVersion($orderId, $context, 'DRAFT');
                $versionContext = $context->createWithVersionId($versionId);

                // add credit line item to order
                $creditLineItemId = Uuid::randomHex();
                $creditLineItem = new LineItem(
                    $creditLineItemId,
                    LineItem::CREDIT_LINE_ITEM_TYPE,
                    null,
                    1
                )
                ;
                $creditLineItem->setLabel('credit-item-1');
                $creditLineItem->setPriceDefinition(new AbsolutePriceDefinition(-20.0));

                $container->get(RecalculationService::class)->addCustomLineItem(
                    $orderId,
                    $creditLineItem,
                    $versionContext,
                );

                // merge the version changes back into LIVE-ORDER-VERSION
                $container->get(VersionManager::class)
                    ->merge($versionId, WriteContext::createFromContext($context));

                $operation->assign([
                    'config' => [
                        'displayLineItems' => true,
                    ],
                ]);
            },
            function (RenderedDocument $rendered): void {
                $rendered = $rendered->getContent();
                static::assertStringContainsString('credit-item-1', $rendered);
            },
        ];
    }

    public function testCreateNewOrderVersionId(): void
    {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);

        $operationInvoice = new DocumentGenerateOperation($orderId);

        static::assertSame($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));

        $this->invoiceRenderer->render(
            [$orderId => $operationInvoice],
            $this->context,
            new DocumentRendererConfig()
        );

        static::assertNotSame($operationInvoice->getOrderVersionId(), Defaults::LIVE_VERSION);
        static::assertTrue($this->orderVersionExists($orderId, $operationInvoice->getOrderVersionId()));
    }

    #[DataProvider('invoiceDataProviderTestIntraCommunityDeliveryLabel')]
    public function testRenderDocumentDisplayOfIntraCommunityDeliveryLabel(
        string $customerType,
        bool $enableIntraCommunityDeliveryLabel,
        bool $enableTaxFreeB2bOption,
        bool $isEuMember,
        bool $validateVat,
        string $vatNumber,
        bool $shouldDisplay
    ): void {
        $cart = $this->generateDemoCart([7]);
        $orderId = $this->persistCart($cart);
        $invoice = new DocumentGenerateOperation($orderId, HtmlRenderer::FILE_EXTENSION);

        $criteria = OrderDocumentCriteriaFactory::create([$orderId]);

        $order = static::getContainer()->get('order.repository')
            ->search($criteria, Context::createDefaultContext())->get($orderId);
        static::assertInstanceOf(OrderEntity::class, $order);

        static::getContainer()->get('customer.repository')->update([[
            'id' => $order->getOrderCustomer()?->getCustomerId(),
            'accountType' => $customerType,
        ]], Context::createDefaultContext());

        $data = [
            'displayAdditionalNoteDelivery' => $enableIntraCommunityDeliveryLabel,
            'fileTypes' => ['pdf', 'html'],
        ];

        $this->upsertBaseConfig($data, InvoiceRenderer::TYPE);

        $orderAddress = $order->getDeliveries()?->first()?->getShippingOrderAddress();

        static::assertNotNull($orderAddress);

        $countryId = $orderAddress->getCountryId();

        $updateData = [
            'id' => $countryId,
            'isEu' => $isEuMember,
            'checkVatIdPattern' => $validateVat,
            'vatIdPattern' => 'DE\d{9}',
        ];

        if ($enableTaxFreeB2bOption) {
            $updateData['companyTax'] = ['amount' => 0, 'enabled' => true, 'currencyId' => Context::createDefaultContext()->getCurrencyId()];
        }

        static::getContainer()->get('country.repository')->upsert([$updateData], Context::createDefaultContext());

        static::getContainer()->get('order_address.repository')->upsert([
            [
                'id' => $orderAddress->getId(),
                'vatId' => $vatNumber,
            ],
        ], Context::createDefaultContext());

        $rendered = $this->invoiceRenderer->render(
            [$orderId => $invoice],
            $this->context,
            new DocumentRendererConfig()
        );

        $data = $rendered->getSuccess();
        static::assertNotEmpty($data);

        if ($shouldDisplay) {
            static::assertStringContainsString('Intra-community delivery (EU)', $data[$orderId]->getContent());
        } else {
            static::assertStringNotContainsString('Intra-community delivery (EU)', $data[$orderId]->getContent());
        }
    }

    public static function invoiceDataProviderTestIntraCommunityDeliveryLabel(): \Generator
    {
        yield 'should not be displayed because the option is disabled' => [
            'customerType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => false,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => false,
        ];

        yield 'should be displayed because all necessary options are set' => [
            'customerType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => true,
        ];

        yield 'should not be displayed because customer account is no B2B account' => [
            'customerType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => false,
        ];

        yield 'shall not be displayed cause customer shipping country is not in "member country" list' => [
            'customerType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => false,
            'validateVat' => false,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => false,
        ];

        yield 'should be displayed because VAT number is valid' => [
            'customerType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => true,
            'vatNumber' => 'DE123456789',
            'shouldDisplay' => true,
        ];

        yield 'should not be displayed because VAT number is invalid' => [
            'customerType' => CustomerEntity::ACCOUNT_TYPE_BUSINESS,
            'enableIntraCommunityDeliveryLabel' => true,
            'enableTaxFreeB2bOption' => true,
            'isEuMember' => true,
            'validateVat' => true,
            'vatNumber' => 'invalid',
            'shouldDisplay' => false,
        ];
    }

    /**
     * @param array<int|string, int> $taxes
     */
    private function generateDemoCart(array $taxes): Cart
    {
        $cart = $this->cartService->createNew('A');

        $products = [];

        $factory = new ProductLineItemFactory(new PriceDefinitionFactory());

        $ids = new IdsCollection();

        $lineItems = [];

        foreach ($taxes as $index => $tax) {
            $price = 100.0 + (int) $index;
            $name = 'product ' . $index;
            $number = 'p' . $index;

            $product = (new ProductBuilder($ids, $number))
                ->price($price)
                ->name($name)
                ->active(true)
                ->tax('test-' . Uuid::randomHex(), $tax)
                ->visibility()
                ->build();

            $products[] = $product;

            $lineItems[] = $factory->create(['id' => $ids->get($number), 'referencedId' => $ids->get($number)], $this->salesChannelContext);
            $this->addTaxDataToSalesChannel($this->salesChannelContext, $product['tax']);
        }

        $this->productRepository->create($products, Context::createDefaultContext());

        return $this->cartService->add($cart, $lineItems, $this->salesChannelContext);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerDoubleOptInRegistrationEvent;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterConfirmRoute;
use Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Debugging\ScriptTraces;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\MailTemplateTestBehaviour;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\DataBag\QueryDataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Controller\RegisterController;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoadedHook;
use Shopware\Storefront\Page\Account\CustomerGroupRegistration\CustomerGroupRegistrationPageLoader;
use Shopware\Storefront\Page\Account\Login\AccountLoginPageLoader;
use Shopware\Storefront\Page\Account\Register\AccountRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoadedHook;
use Shopware\Storefront\Page\Checkout\Register\CheckoutRegisterPageLoader;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoader;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoader;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\HttpFoundation\Session\Session;

/**
 * @internal
 */
#[Package('checkout')]
class RegisterControllerTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MailTemplateTestBehaviour;
    use StorefrontControllerTestBehaviour;

    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);

        $token = Uuid::randomHex();
        $this->salesChannelContext = $salesChannelContextFactory->create($token, TestDefaults::SALES_CHANNEL);

        $session = $this->getSession();
        static::assertInstanceOf(FlashBagAwareSessionInterface::class, $session);
        $session->getFlashBag()->clear();
    }

    public function testGuestRegisterWithRequirePasswordConfirmation(): void
    {
        $container = static::getContainer();

        $customerRepository = $container->get('customer.repository');

        $config = static::getContainer()->get(SystemConfigService::class);

        $systemConfigServiceMock = $this->createMock(SystemConfigService::class);

        $systemConfigServiceMock
            ->method('get')
            ->willReturnCallback(function (string $key) use ($config) {
                if ($key === 'core.loginRegistration.requirePasswordConfirmation') {
                    return true;
                }

                return $config->get($key);
            });

        $registerController = $this->getRegisterController($container, $systemConfigServiceMock, $customerRepository);

        $data = $this->getRegistrationData();

        $request = $this->createRequest();

        $response = $registerController->register($request, $data, $this->salesChannelContext);

        $customers = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM customer WHERE email = :mail', ['mail' => $data->get('email')]);

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $customers);
        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
    }

    public function testGuestRegister(): void
    {
        $data = $this->getRegistrationData();

        $request = $this->createRequest();

        $response = static::getContainer()->get(RegisterController::class)->register($request, $data, $this->salesChannelContext);

        $customers = static::getContainer()->get(Connection::class)
            ->fetchAllAssociative('SELECT * FROM customer WHERE email = :mail', ['mail' => $data->get('email')]);

        static::assertSame(200, $response->getStatusCode());
        static::assertCount(1, $customers);
        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));
    }

    public function testRegisterWithDoubleOptIn(): void
    {
        $container = static::getContainer();

        $customerRepository = $container->get('customer.repository');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController = $this->getRegisterController($container, $systemConfigService, $customerRepository);

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        $response = $registerController->register($request, $data, $this->salesChannelContext);

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account/register', $response->getTargetUrl());

        $session = $this->getSession();
        static::assertInstanceOf(Session::class, $session);
        $success = $session->getFlashBag()->get('success');

        static::assertNotEmpty($success);
        static::assertSame($container->get('translator')->trans('account.optInRegistrationAlert'), $success[0]);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $event);
        static::assertMailEvent(CustomerDoubleOptInRegistrationEvent::class, $event, $this->salesChannelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($data->all()), $event);

        static::assertStringEndsWith('&redirectTo=frontend.checkout.confirm.page', $event->getConfirmUrl());
    }

    public function testRegisterWithDoubleOptInDomainChanged(): void
    {
        $container = static::getContainer();

        $customerRepository = $container->get('customer.repository');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);
        $systemConfigService->set('core.loginRegistration.doubleOptInDomain', 'https://test.test.com');

        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController = $this->getRegisterController($container, $systemConfigService, $customerRepository);

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        $response = $registerController->register($request, $data, $this->salesChannelContext);

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/account/register', $response->getTargetUrl());

        $session = $request->getSession();
        static::assertInstanceOf(Session::class, $session);
        $success = $session->getFlashBag()->get('success');

        static::assertNotEmpty($success);
        static::assertSame($container->get('translator')->trans('account.optInRegistrationAlert'), $success[0]);

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $event);
        static::assertMailEvent(CustomerDoubleOptInRegistrationEvent::class, $event, $this->salesChannelContext);
        static::assertMailRecipientStructEvent($this->getMailRecipientStruct($data->all()), $event);

        static::assertStringStartsWith('https://test.test.com', $event->getConfirmUrl());
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', false);
        $systemConfigService->set('core.loginRegistration.doubleOptInDomain', null);
    }

    public function testConfirmRegisterWithRedirectTo(): void
    {
        $container = static::getContainer();

        /** @var EntityRepository<CustomerCollection> $customerRepository */
        $customerRepository = $container->get('customer.repository');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController = $this->getRegisterController($container, $systemConfigService, $customerRepository);

        $registerController->setContainer($container);

        $data = $this->getRegistrationData(false);
        $data->add(['redirectTo' => 'frontend.checkout.confirm.page']);

        $request = $this->createRequest();

        $event = null;
        $this->catchEvent(CustomerDoubleOptInRegistrationEvent::class, $event);

        $registerController->register($request, $data, $this->salesChannelContext);

        static::assertFalse($request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));

        static::assertInstanceOf(CustomerDoubleOptInRegistrationEvent::class, $event);

        $customer = $customerRepository->search(new Criteria([$event->getCustomerId()]), $this->salesChannelContext->getContext())->getEntities();
        $queryData = new QueryDataBag();
        $queryData->set('redirectTo', 'frontend.checkout.confirm.page');
        $queryData->set('hash', $customer->first()?->getHash());
        $queryData->set('em', Hasher::hash($event->getCustomer()->getEmail(), 'sha1'));

        $response = $registerController->confirmRegistration($this->salesChannelContext, $queryData);

        static::assertTrue($request->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT));

        static::assertSame(302, $response->getStatusCode());
        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/checkout/confirm', $response->getTargetUrl());
    }

    public function testAccountRegisterPageLoadedHookScriptsAreExecuted(): void
    {
        $response = $this->request('GET', '/account/register', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(AccountRegisterPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCustomerGroupRegistrationPageLoadedHookScriptsAreExecuted(): void
    {
        $ids = new IdsCollection();
        $this->createCustomerGroup($ids);

        $response = $this->request('GET', 'customer-group-registration/' . $ids->get('group'), []);
        static::assertSame(200, $response->getStatusCode(), print_r($response->getContent(), true));

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CustomerGroupRegistrationPageLoadedHook::HOOK_NAME, $traces);
    }

    public function testCheckoutRegisterPageLoadedHookScriptsAreExecuted(): void
    {
        $productNumber = ' p1';

        $this->createProduct(Uuid::randomHex(), $productNumber);

        $this->request(
            'POST',
            '/checkout/product/add-by-number',
            $this->tokenize('frontend.checkout.product.add-by-number', [
                'number' => $productNumber,
            ])
        );

        $response = $this->request('GET', '/checkout/register', []);
        static::assertSame(200, $response->getStatusCode());

        $traces = static::getContainer()->get(ScriptTraces::class)->getTraces();

        static::assertArrayHasKey(CheckoutRegisterPageLoadedHook::HOOK_NAME, $traces);
    }

    /**
     * @param array<string|int, mixed> $customerData
     */
    private function getMailRecipientStruct(array $customerData): MailRecipientStruct
    {
        return new MailRecipientStruct([
            (string) $customerData['email'] => $customerData['firstName'] . ' ' . $customerData['lastName'],
        ]);
    }

    private function createRequest(): Request
    {
        $request = new Request();
        $request->setSession($this->getSession());
        $request->request->add(['errorRoute' => 'frontend.checkout.register.page']);
        $request->attributes->add(['_route' => 'frontend.checkout.register.page', SalesChannelRequest::ATTRIBUTE_IS_SALES_CHANNEL_REQUEST => true]);
        $request->attributes->set(RequestTransformer::STOREFRONT_URL, 'shopware.test');

        static::getContainer()->get('request_stack')->push($request);

        return $request;
    }

    private function getRegistrationData(?bool $isGuest = true): RequestDataBag
    {
        $data = [
            'accountType' => CustomerEntity::ACCOUNT_TYPE_PRIVATE,
            'email' => 'max.mustermann@example.com',
            'emailConfirmation' => 'max.mustermann@example.com',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'storefrontUrl' => 'http://localhost',
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'countryId' => $this->getValidCountryId(),
                'street' => 'Musterstrasse 13',
                'zipcode' => '48599',
                'city' => 'Epe',
            ],
        ];

        if (!$isGuest) {
            $data['createCustomerAccount'] = true;
            $data['password'] = TestDefaults::HASHED_PASSWORD;
        }

        return new RequestDataBag($data);
    }

    private function createCustomerGroup(IdsCollection $ids): void
    {
        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        static::getContainer()->get('customer_group.repository')->create([
            [
                'id' => $ids->create('group'),
                'registrationActive' => true,
                'name' => 'test',
                'registrationSalesChannels' => [
                    [
                        'id' => $salesChannel->getId(),
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }

    private function createProduct(string $productId, string $productNumber): void
    {
        $taxId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $salesChannel = static::getContainer()->get('sales_channel.repository')->search(
            (new Criteria())->addFilter(
                new EqualsFilter('typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                new EqualsFilter('domains.url', $_SERVER['APP_URL'])
            ),
            Context::createDefaultContext()
        )->getEntities()->first();

        static::assertInstanceOf(SalesChannelEntity::class, $salesChannel);

        $product = [
            'id' => $productId,
            'name' => 'Test product',
            'productNumber' => $productNumber,
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15.99, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['id' => $taxId, 'name' => 'testTaxRate', 'taxRate' => 15],
            'categories' => [
                ['id' => $productId, 'name' => 'Test category'],
            ],
            'visibilities' => [
                [
                    'id' => $productId,
                    'salesChannelId' => $salesChannel->getId(),
                    'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
        ];
        static::getContainer()->get('product.repository')->create([$product], $context);
    }

    /**
     * @param EntityRepository<CustomerCollection> $customerRepository
     */
    private function getRegisterController(
        ContainerInterface $container,
        SystemConfigService $systemConfigService,
        EntityRepository $customerRepository
    ): RegisterController {
        return new RegisterController(
            $container->get(AccountLoginPageLoader::class),
            $container->get(RegisterRoute::class),
            $container->get(RegisterConfirmRoute::class),
            $container->get(CartService::class),
            $container->get(CheckoutRegisterPageLoader::class),
            $systemConfigService,
            $customerRepository,
            $this->createMock(CustomerGroupRegistrationPageLoader::class),
            $container->get('sales_channel_domain.repository'),
            $container->get(HeaderPageletLoader::class),
            $container->get(FooterPageletLoader::class),
        );
    }
}

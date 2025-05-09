<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Confirm;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Address\Error\AddressValidationError;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory;
use Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerZipCode;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\BuildValidationEvent;
use Shopware\Core\Framework\Validation\DataValidationDefinition;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPage;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Confirm\CheckoutConfirmPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(CheckoutConfirmPageLoader::class)]
class CheckoutConfirmPageLoaderTest extends TestCase
{
    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new CheckoutConfirmPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new CheckoutConfirmPage();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testPaymentAndShippingMethodsAreSetToPage(): void
    {
        $paymentMethods = new PaymentMethodCollection([
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $response = new CheckoutGatewayRouteResponse(
            $paymentMethods,
            $shippingMethods,
            new ErrorCollection()
        );

        $checkoutGatewayRoute = $this->createMock(CheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->method('load')
            ->withAnyParameters()
            ->willReturn($response);

        $page = $this->createLoader(checkoutGatewayRoute: $checkoutGatewayRoute)->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertSame($paymentMethods, $page->getPaymentMethods());
        static::assertSame($shippingMethods, $page->getShippingMethods());
    }

    public function testCustomerNotLoggedInException(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn(null);

        $expected = CartException::customerNotLoggedIn()::class;

        $this->expectException($expected);
        $this->expectExceptionMessage('Customer is not logged in');

        $this->createLoader()->load(new Request(), $context);
    }

    public function testViolationsAreAddedAsCartErrorsWithSameAddress(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Test error',
                null,
                [],
                'root',
                null,
                'invalidValue'
            ),
        ]);

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->method('getViolations')
            ->willReturn($violations);

        $cart = new Cart('test');

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('get')
            ->willReturn($cart);

        $page = $this->createLoader(cartService: $cartService, validator: $validator)->load(new Request(), $this->getContextWithDummyCustomer());

        static::assertCount(1, $page->getCart()->getErrors());
        static::assertArrayHasKey('billing-address-invalid', $page->getCart()->getErrors()->getElements());

        $error = $page->getCart()->getErrors()->first();

        static::assertNotNull($error);
        static::assertInstanceOf(AddressValidationError::class, $error);
        static::assertTrue($error->isBillingAddress());

        static::assertCount(1, $error->getViolations());

        $violation = $error->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());
    }

    public function testViolationsAreAddedAsCartErrorsWithDifferentAddresses(): void
    {
        $violations = new ConstraintViolationList([
            new ConstraintViolation(
                'Test error',
                null,
                [],
                'root',
                null,
                'invalidValue'
            ),
        ]);

        $validator = $this->createMock(DataValidator::class);
        $validator
            ->method('getViolations')
            ->willReturn($violations);

        $cart = new Cart('test');

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('get')
            ->willReturn($cart);

        $context = $this->getContextWithDummyCustomer();

        static::assertNotNull($context->getCustomer());

        // different shipping address
        $context->getCustomer()->assign([
            'activeShippingAddress' => (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]),
        ]);

        $page = $this->createLoader(cartService: $cartService, validator: $validator)->load(new Request(), $context);

        static::assertCount(2, $page->getCart()->getErrors());
        static::assertArrayHasKey('billing-address-invalid', $page->getCart()->getErrors()->getElements());
        static::assertArrayHasKey('shipping-address-invalid', $page->getCart()->getErrors()->getElements());

        $billingAddressError = $page->getCart()->getErrors()->first();

        static::assertNotNull($billingAddressError);
        static::assertInstanceOf(AddressValidationError::class, $billingAddressError);
        static::assertTrue($billingAddressError->isBillingAddress());

        static::assertCount(1, $billingAddressError->getViolations());

        $violation = $billingAddressError->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());

        $shippingAddressError = $page->getCart()->getErrors()->first();

        static::assertNotNull($shippingAddressError);
        static::assertInstanceOf(AddressValidationError::class, $shippingAddressError);
        static::assertTrue($shippingAddressError->isBillingAddress());

        static::assertCount(1, $shippingAddressError->getViolations());

        $violation = $shippingAddressError->getViolations()->get(0);

        static::assertInstanceOf(ConstraintViolation::class, $violation);
        static::assertSame('Test error', $violation->getMessage());
        static::assertSame('root', $violation->getRoot());
        static::assertSame('invalidValue', $violation->getInvalidValue());
    }

    public function testValidatorNotCalledIfNoAddressGiven(): void
    {
        $validator = $this->createMock(DataValidator::class);
        $validator
            ->expects($this->never())
            ->method('getViolations');

        $checkoutConfirmPageLoader = $this->createLoader(validator: $validator);

        $context = $this->getContextWithDummyCustomer();

        static::assertNotNull($context->getCustomer());

        $context->getCustomer()->assign([
            'activeBillingAddress' => null,
            'activeShippingAddress' => null,
        ]);

        $checkoutConfirmPageLoader->load(new Request(), $context);
    }

    public function testValidationEventIsDispatched(): void
    {
        $eventDispatcher = new CollectingEventDispatcher();

        $addressValidationMock = $this->createMock(AddressValidationFactory::class);

        $checkoutConfirmPageLoader = $this->createLoader(
            eventDispatcher: $eventDispatcher,
            addressValidationFactory: $addressValidationMock,
        );

        $addressValidationMock->expects($this->exactly(2))->method('create')->willReturnOnConsecutiveCalls(
            new DataValidationDefinition('address.create'),
            new DataValidationDefinition('address.update'),
        );

        $checkoutConfirmPageLoader->load(new Request(), $this->getContextWithDummyCustomer());

        $events = $eventDispatcher->getEvents();
        static::assertCount(3, $events);

        static::assertInstanceOf(BuildValidationEvent::class, $events['framework.validation.address.create']);
        static::assertInstanceOf(BuildValidationEvent::class, $events['framework.validation.address.update']);
        static::assertInstanceOf(CheckoutConfirmPageLoadedEvent::class, $events[0]);
    }

    public function testCartServiceIsCalledTaxedAndWithNoCaching(): void
    {
        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->expects($this->once())
            ->method('get')
            ->with(null, static::isInstanceOf(SalesChannelContext::class), false, true);

        $checkoutConfirmPageLoader = $this->createLoader(
            cartService: $cartService,
        );

        $checkoutConfirmPageLoader->load(new Request(), $this->getContextWithDummyCustomer());
    }

    public function testValidationEventIsDispatchedWithZipcodeDefinition(): void
    {
        $countryId = Uuid::randomHex();

        $cart = new Cart('test');

        $cartService = $this->createMock(StorefrontCartFacade::class);
        $cartService
            ->method('get')
            ->willReturn($cart);

        $addressValidation = $this->createMock(DataValidationFactoryInterface::class);
        $addressValidation->method('create')->willReturn(new DataValidationDefinition('address.create'));

        $dispatcher = $this->createMock(EventDispatcher::class);
        $dispatcher->method('dispatch')->willReturnCallback(function ($validationEvent) use ($countryId) {
            if (!$validationEvent instanceof BuildValidationEvent) {
                return $validationEvent;
            }

            $properties = $validationEvent->getDefinition()->getProperties();
            static::assertArrayHasKey('zipcode', $properties);
            $zipcode = $properties['zipcode'][0];
            static::assertNotNull($zipcode);
            static::assertInstanceOf(CustomerZipCode::class, $zipcode);

            $message = $zipcode->getMessage();

            static::assertSame($message, (new CustomerZipCode(['countryId' => $countryId]))->getMessage());

            return $validationEvent;
        });

        $checkoutConfirmPageLoader = $this->createLoader(
            eventDispatcher: $dispatcher,
            cartService: $cartService,
            addressValidationFactory: $addressValidation,
        );

        $context = $this->getContextWithDummyCustomer();

        $checkoutConfirmPageLoader->load(new Request(), $context);
    }

    private function createLoader(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?StorefrontCartFacade $cartService = null,
        ?CheckoutGatewayRoute $checkoutGatewayRoute = null,
        ?GenericPageLoader $pageLoader = null,
        ?DataValidationFactoryInterface $addressValidationFactory = null,
        ?DataValidator $validator = null,
    ): CheckoutConfirmPageLoader {
        return new CheckoutConfirmPageLoader(
            $eventDispatcher ?? $this->createMock(EventDispatcherInterface::class),
            $cartService ?? $this->createMock(StorefrontCartFacade::class),
            $checkoutGatewayRoute ?? $this->createMock(CheckoutGatewayRoute::class),
            $pageLoader ?? $this->createMock(GenericPageLoader::class),
            $addressValidationFactory ?? $this->createMock(DataValidationFactoryInterface::class),
            $validator ?? $this->createMock(DataValidator::class),
            $this->createMock(AbstractTranslator::class),
        );
    }

    private function getContextWithDummyCustomer(): SalesChannelContext
    {
        $address = (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => Uuid::randomHex()]);

        $customer = new CustomerEntity();
        $customer->assign([
            'activeBillingAddress' => $address,
            'activeShippingAddress' => $address,
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn($customer);

        return $context;
    }
}

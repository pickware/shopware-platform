<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Payment\Cart\Error\PaymentMethodBlockedError;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\Cart\Error\ShippingMethodBlockedError;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Checkout\Cart\Error\PaymentMethodChangedError;
use Shopware\Storefront\Checkout\Cart\Error\ShippingMethodChangedError;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Checkout\Payment\BlockedPaymentMethodSwitcher;
use Shopware\Storefront\Checkout\Shipping\BlockedShippingMethodSwitcher;

/**
 * @internal
 */
#[CoversClass(StorefrontCartFacade::class)]
class StorefrontCartFacadeTest extends TestCase
{
    public function testGetNoBlockedMethods(): void
    {
        $cart = $this->getCart();
        $cart->setErrors($this->getCartErrorCollection());

        $cartFacade = $this->getStorefrontCartFacade($cart);
        $salesChannelContext = $this->getSalesChannelContext();
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertEquals($this->getCart(), $returnedCart);
    }

    public function testGetBlockedShippingMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->expects($this->exactly(2))
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods) use ($salesChannelContext): void {
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());

                    $salesChannelContext->getShippingMethod()->setName($shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        $filtered = $errorCollection->filterInstance(ShippingMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'fallback-shipping-method-id',
            'newShippingMethodName' => 'fallback-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true));
        static::assertNotSame($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(false, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->expects($this->exactly(2))
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods) use ($salesChannelContext): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());

                    $salesChannelContext->getPaymentMethod()->setName($paymentMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnOriginalMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        $filtered = $errorCollection->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'oldPaymentMethodId' => 'original-payment-method-id',
            'oldPaymentMethodName' => 'original-payment-method-name',
            'newPaymentMethodId' => 'fallback-payment-method-id',
            'newPaymentMethodName' => 'fallback-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(false, true));
        static::assertNotSame($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentAndShippingMethodAllowFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->expects($this->exactly(2))
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods) use ($salesChannelContext): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());

                    $salesChannelContext->getPaymentMethod()->setName($paymentMethod->getName());
                    $salesChannelContext->getShippingMethod()->setName($shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        $filtered = $errorCollection->filterInstance(PaymentMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodChangedError::class, $error);
        static::assertSame([
            'oldPaymentMethodId' => 'original-payment-method-id',
            'oldPaymentMethodName' => 'original-payment-method-name',
            'newPaymentMethodId' => 'fallback-payment-method-id',
            'newPaymentMethodName' => 'fallback-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $filtered = $errorCollection->filterInstance(ShippingMethodChangedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodChangedError::class, $error);
        static::assertSame([
            'oldShippingMethodId' => 'original-shipping-method-id',
            'oldShippingMethodName' => 'original-shipping-method-name',
            'newShippingMethodId' => 'fallback-shipping-method-id',
            'newShippingMethodName' => 'fallback-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true, true));
        static::assertNotSame($controlCart, $returnedCart);
    }

    public function testGetBlockedShippingMethodNoFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->expects($this->never())
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('original-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade($cart);
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertCount(0, $errorCollection->filterInstance(ShippingMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(ShippingMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getShippingMethodId(),
            'name' => 'original-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentMethodNoFallback(): void
    {
        $errorCollection = $this->getCartErrorCollection(false, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->expects($this->never())
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('original-payment-method-name', $paymentMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade($cart);
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertCount(0, $errorCollection->filterInstance(PaymentMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(PaymentMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getPaymentMethodId(),
            'name' => 'original-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(false, true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testGetBlockedPaymentAndShippingMethodWillUpdateOriginalContext(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('original-shipping-method-id');
        $shippingMethod->setName('original-shipping-method-name');

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('original-payment-method-id');
        $paymentMethod->setName('original-payment-method-name');

        $salesChannelContext = Generator::generateSalesChannelContext(paymentMethod: $paymentMethod, shippingMethod: $shippingMethod);

        $ruleIds = ['id'];
        $areaRuleIds = ['area' => ['id']];

        $cartCalculator = $this->createMock(CartCalculator::class);
        $cartCalculator
            ->method('calculate')
            ->willReturnCallback(static function (Cart $cart, SalesChannelContext $context) use ($ruleIds, $areaRuleIds): Cart {
                $context->setRuleIds($ruleIds);
                $context->setAreaRuleIds($areaRuleIds);

                return $cart;
            });

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherReturnFallbackMethod(...),
            $this->callbackPaymentMethodSwitcherReturnFallbackMethod(...),
            $cartCalculator
        );

        $cartFacade->get('', $salesChannelContext);

        static::assertSame('fallback-payment-method-name', $salesChannelContext->getPaymentMethod()->getName());
        static::assertSame('fallback-shipping-method-name', $salesChannelContext->getShippingMethod()->getName());
        static::assertSame($ruleIds, $salesChannelContext->getRuleIds());
        static::assertSame($areaRuleIds, $salesChannelContext->getAreaRuleIds());
    }

    public function testGetUnswitchableCart(): void
    {
        $errorCollection = $this->getCartErrorCollection(true, true);

        $cart = $this->getCart();
        $cart->setErrors($errorCollection);

        $salesChannelContext = $this->getSalesChannelContext();
        $salesChannelContext
            ->method('assign')
            ->willReturnCallback(
                function ($newMethods): void {
                    $paymentMethod = $newMethods['paymentMethod'];
                    static::assertInstanceOf(PaymentMethodEntity::class, $paymentMethod);
                    static::assertSame('fallback-payment-method-name', $paymentMethod->getName());

                    $shippingMethod = $newMethods['shippingMethod'];
                    static::assertInstanceOf(ShippingMethodEntity::class, $shippingMethod);
                    static::assertSame('fallback-shipping-method-name', $shippingMethod->getName());
                }
            );

        $cartFacade = $this->getStorefrontCartFacade(
            $cart,
            $this->callbackShippingMethodSwitcherUnswitchableCart(...),
            $this->callbackPaymentMethodSwitcherUnswitchableCart(...)
        );
        $returnedCart = $cartFacade->get('', $salesChannelContext);

        static::assertCount(0, $errorCollection->filterInstance(ShippingMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(ShippingMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(ShippingMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getShippingMethodId(),
            'name' => 'original-shipping-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        static::assertCount(0, $errorCollection->filterInstance(PaymentMethodChangedError::class));
        $filtered = $errorCollection->filterInstance(PaymentMethodBlockedError::class);
        static::assertCount(1, $filtered);
        $error = $filtered->first();
        static::assertInstanceOf(PaymentMethodBlockedError::class, $error);
        static::assertSame([
            'id' => $error->getPaymentMethodId(),
            'name' => 'original-payment-method-name',
            'reason' => 'reason',
        ], $error->getParameters());

        $controlCart = $this->getCart();
        $controlCart->setErrors($this->getCartErrorCollection(true, true));
        static::assertEquals($controlCart, $returnedCart);
    }

    public function testCartServiceIsCalledTaxedAndWithNoCaching(): void
    {
        $cartService = static::createMock(CartService::class);
        $cartService
            ->expects($this->once())
            ->method('getCart')
            ->with(
                'token',
                static::isInstanceOf(SalesChannelContext::class),
                false,
                true
            );

        $cartFacade = new StorefrontCartFacade(
            $cartService,
            static::createMock(BlockedShippingMethodSwitcher::class),
            static::createMock(BlockedPaymentMethodSwitcher::class),
            static::createMock(ContextSwitchRoute::class),
            static::createMock(CartCalculator::class),
            static::createMock(AbstractCartPersister::class),
        );

        $cartFacade->get(
            'token',
            static::createMock(SalesChannelContext::class),
            false,
            true
        );
    }

    public function callbackShippingMethodSwitcherReturnOriginalMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodEntity
    {
        return $salesChannelContext->getShippingMethod();
    }

    public function callbackShippingMethodSwitcherReturnFallbackMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fallback-shipping-method-id');
        $shippingMethod->setName('fallback-shipping-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }

            static::assertNotNull($shippingMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->remove($error->getId());
            $errors->add(new ShippingMethodChangedError(
                oldShippingMethodId: $error->getShippingMethodId(),
                oldShippingMethodName: $error->getName(),
                newShippingMethodId: $shippingMethod->getId(),
                newShippingMethodName: $shippingMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $shippingMethod;
    }

    public function callbackShippingMethodSwitcherUnswitchableCart(ErrorCollection $errors, SalesChannelContext $salesChannelContext): ShippingMethodEntity
    {
        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('fallback-shipping-method-id');
        $shippingMethod->setName('fallback-shipping-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof ShippingMethodBlockedError) {
                continue;
            }

            static::assertNotNull($shippingMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->add(new ShippingMethodChangedError(
                oldShippingMethodId: $error->getShippingMethodId(),
                oldShippingMethodName: $error->getName(),
                newShippingMethodId: $shippingMethod->getId(),
                newShippingMethodName: $shippingMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $shippingMethod;
    }

    public function callbackPaymentMethodSwitcherReturnOriginalMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodEntity
    {
        return $salesChannelContext->getPaymentMethod();
    }

    public function callbackPaymentMethodSwitcherReturnFallbackMethod(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('fallback-payment-method-id');
        $paymentMethod->setName('fallback-payment-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }

            static::assertNotNull($paymentMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->remove($error->getId());
            $errors->add(new PaymentMethodChangedError(
                oldPaymentMethodId: $error->getPaymentMethodId(),
                oldPaymentMethodName: $error->getName(),
                newPaymentMethodId: $paymentMethod->getId(),
                newPaymentMethodName: $paymentMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $paymentMethod;
    }

    public function callbackPaymentMethodSwitcherUnswitchableCart(ErrorCollection $errors, SalesChannelContext $salesChannelContext): PaymentMethodEntity
    {
        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('fallback-payment-method-id');
        $paymentMethod->setName('fallback-payment-method-name');

        foreach ($errors as $error) {
            if (!$error instanceof PaymentMethodBlockedError) {
                continue;
            }

            static::assertNotNull($paymentMethod->getName());

            // Exchange cart blocked warning with notice
            $errors->add(new PaymentMethodChangedError(
                oldPaymentMethodId: $error->getPaymentMethodId(),
                oldPaymentMethodName: $error->getName(),
                newPaymentMethodId: $paymentMethod->getId(),
                newPaymentMethodName: $paymentMethod->getName(),
                reason: $error->getReason(),
            ));
        }

        return $paymentMethod;
    }

    private function getCart(): Cart
    {
        $cart = new Cart('cart-token');
        $cart->add(
            (new LineItem('line-item-id-1', 'line-item-type-1'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-1')
                ->assign(['uniqueIdentifier' => 'line-item-id-1'])
        )->add(
            (new LineItem('line-item-id-2', 'line-item-type-2'))
                ->setPrice(new CalculatedPrice(1, 1, new CalculatedTaxCollection(), new TaxRuleCollection()))
                ->setLabel('line-item-label-2')
                ->assign(['uniqueIdentifier' => 'line-item-id-2'])
        );

        return $cart;
    }

    private function getCartErrorCollection(bool $blockShippingMethod = false, bool $blockPaymentMethod = false): ErrorCollection
    {
        $cartErrors = new ErrorCollection();
        if ($blockShippingMethod) {
            $cartErrors->add(
                new ShippingMethodBlockedError(
                    id: 'original-shipping-method-id',
                    name: 'original-shipping-method-name',
                    reason: 'reason',
                )
            );
        }

        if ($blockPaymentMethod) {
            $cartErrors->add(
                new PaymentMethodBlockedError(
                    id: 'original-payment-method-id',
                    name: 'original-payment-method-name',
                    reason: 'reason',
                )
            );
        }

        return $cartErrors;
    }

    /**
     * @param callable(ErrorCollection, SalesChannelContext): ShippingMethodEntity|null $shippingSwitcherCallbackMethod
     * @param callable(ErrorCollection, SalesChannelContext): PaymentMethodEntity|null $paymentSwitcherCallbackMethod
     */
    private function getStorefrontCartFacade(
        Cart $cart,
        ?callable $shippingSwitcherCallbackMethod = null,
        ?callable $paymentSwitcherCallbackMethod = null,
        ?CartCalculator $cartCalculator = null,
    ): StorefrontCartFacade {
        $cartService = $this->createMock(CartService::class);
        $cartService->method('getCart')->willReturn($cart);

        $blockedShippingMethodSwitcher = $this->createMock(BlockedShippingMethodSwitcher::class);
        $blockedShippingMethodSwitcher->method('switch')->willReturnCallback($shippingSwitcherCallbackMethod ?? $this->callbackShippingMethodSwitcherReturnOriginalMethod(...));

        $blockedPaymentMethodSwitcher = $this->createMock(BlockedPaymentMethodSwitcher::class);
        $blockedPaymentMethodSwitcher->method('switch')->willReturnCallback($paymentSwitcherCallbackMethod ?? $this->callbackPaymentMethodSwitcherReturnOriginalMethod(...));

        $contextSwitchRoute = $this->createMock(ContextSwitchRoute::class);

        if (!$cartCalculator) {
            $cartCalculator = $this->createMock(CartCalculator::class);
            $cartCalculator->method('calculate')->willReturnArgument(0);
        }

        $cartPersister = $this->createMock(CartPersister::class);

        return new StorefrontCartFacade(
            $cartService,
            $blockedShippingMethodSwitcher,
            $blockedPaymentMethodSwitcher,
            $contextSwitchRoute,
            $cartCalculator,
            $cartPersister,
        );
    }

    /**
     * @return MockObject&SalesChannelContext
     */
    private function getSalesChannelContext(): MockObject
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(TestDefaults::SALES_CHANNEL);
        $salesChannel->setLanguageId(Defaults::LANGUAGE_SYSTEM);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getSalesChannel')->willReturn($salesChannel);
        $salesChannelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $shippingMethod = new ShippingMethodEntity();
        $shippingMethod->setId('original-shipping-method-id');
        $shippingMethod->setName('original-shipping-method-name');

        $paymentMethod = new PaymentMethodEntity();
        $paymentMethod->setId('original-payment-method-id');
        $paymentMethod->setName('original-payment-method-name');

        $salesChannelContext->method('getShippingMethod')->willReturn($shippingMethod);
        $salesChannelContext->method('getPaymentMethod')->willReturn($paymentMethod);

        return $salesChannelContext;
    }
}

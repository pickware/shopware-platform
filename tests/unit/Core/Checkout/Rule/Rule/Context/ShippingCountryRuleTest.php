<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Customer\Rule\ShippingCountryRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedOperatorException;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(ShippingCountryRule::class)]
class ShippingCountryRuleTest extends TestCase
{
    public function testEquals(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1'], 'operator' => ShippingCountryRule::OPERATOR_EQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotEquals(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1'], 'operator' => ShippingCountryRule::OPERATOR_NEQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-1');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testEqualsWithMultipleCountries(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], 'operator' => ShippingCountryRule::OPERATOR_EQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testNotEqualsWithMultipleCountries(): void
    {
        $rule = (new ShippingCountryRule())->assign(['countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'], 'operator' => ShippingCountryRule::OPERATOR_NEQ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    #[DataProvider('unsupportedOperators')]
    public function testUnsupportedOperators(string $operator): void
    {
        $this->expectExceptionObject(RuleException::unsupportedOperator($operator, RuleComparison::class));

        $rule = (new ShippingCountryRule())
            ->assign([
                'countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'],
                'operator' => $operator,
            ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        $rule->match(new CartRuleScope($cart, $context));
    }

    public function testUnsupportedOperatorMessage(): void
    {
        $rule = (new ShippingCountryRule())
            ->assign([
                'countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'],
                'operator' => ShippingCountryRule::OPERATOR_GTE,
            ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        try {
            $rule->match(new CartRuleScope($cart, $context));
        } catch (RuleException $e) {
            static::assertSame(ShippingCountryRule::OPERATOR_GTE, $e->getParameter('operator'));
            static::assertSame(RuleComparison::class, $e->getParameter('class'));
        }
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testUnsupportedOperatorMessageDeprecated(): void
    {
        $rule = (new ShippingCountryRule())
            ->assign([
                'countryIds' => ['SWAG-AREA-COUNTRY-ID-1', 'SWAG-AREA-COUNTRY-ID-2', 'SWAG-AREA-COUNTRY-ID-3'],
                'operator' => ShippingCountryRule::OPERATOR_GTE,
            ]);

        $cart = new Cart('test');

        $context = $this->createMock(SalesChannelContext::class);

        $country = new CountryEntity();
        $country->setId('SWAG-AREA-COUNTRY-ID-2');

        $context
            ->method('getShippingLocation')
            ->willReturn(ShippingLocation::createFromCountry($country));

        try {
            $rule->match(new CartRuleScope($cart, $context));
        } catch (UnsupportedOperatorException $e) {
            static::assertSame(ShippingCountryRule::OPERATOR_GTE, $e->getOperator());
            static::assertSame(RuleComparison::class, $e->getClass());
        }
    }

    /**
     * @return array<array{0: string}>
     */
    public static function unsupportedOperators(): array
    {
        return [
            [''],
            [ShippingCountryRule::OPERATOR_GTE],
            [ShippingCountryRule::OPERATOR_LTE],
        ];
    }
}

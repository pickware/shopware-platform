<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfManufacturerRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[Group('rules')]
class LineItemOfManufacturerRuleTest extends TestCase
{
    use CartRuleHelperTrait;
    use IntegrationTestBehaviour;

    private LineItemOfManufacturerRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemOfManufacturerRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemOfManufacturer', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('manufacturerIds', $ruleConstraints);
        static::assertArrayHasKey('operator', $ruleConstraints);
    }

    /**
     * @param array<string> $manufacturerIds
     */
    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesCorrectWithLineItemScope(
        array $manufacturerIds,
        string $operator,
        string $lineItemManufacturerId,
        bool $expected
    ): void {
        $this->rule->assign([
            'manufacturerIds' => $manufacturerIds,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithManufacturer($lineItemManufacturerId),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<array<string>|string|bool>>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'single product / equal / match product manufacturer' => [['1', '2'], Rule::OPERATOR_EQ, '1', true],
            'single product / equal / no match' => [['1', '2'], Rule::OPERATOR_EQ, '3', false],
            'single product / not equal / match product manufacturer' => [['1', '2'], Rule::OPERATOR_NEQ, '3', true],
        ];
    }

    /**
     * @param array<string> $manufacturerIds
     */
    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        array $manufacturerIds,
        string $operator,
        string $lineItemManufacturerId,
        bool $expected
    ): void {
        $this->rule->assign([
            'manufacturerIds' => $manufacturerIds,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithManufacturer('1'),
            $this->createLineItemWithManufacturer($lineItemManufacturerId),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @param array<string> $manufacturerIds
     */
    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        array $manufacturerIds,
        string $operator,
        string $lineItemManufacturerId,
        bool $expected
    ): void {
        $this->rule->assign([
            'manufacturerIds' => $manufacturerIds,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithManufacturer('1'),
            $this->createLineItemWithManufacturer($lineItemManufacturerId),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection)->setPayloadValue('manufacturerId', '1');
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<array<string>|string|bool>>
     */
    public static function getCartRuleScopeTestData(): array
    {
        return [
            'multiple products / equal / match product manufacturer' => [['1', '2'], Rule::OPERATOR_EQ, '2', true],
            'multiple products / equal / no match' => [['4', '5'], Rule::OPERATOR_EQ, '2', false],
            'multiple products / not equal / match product manufacturer' => [['5', '6'], Rule::OPERATOR_NEQ, '2', true],
            'multiple products / not equal / no match product manufacturer' => [['1', '2'], Rule::OPERATOR_NEQ, '2', false],
            'multiple products / empty / match product manufacturer' => [['1', '2'], Rule::OPERATOR_EMPTY, '', true],
            'multiple products / empty/ no match product manufacturer' => [['1', '2'], Rule::OPERATOR_EMPTY, '2', false],
        ];
    }

    public function testNotAvailableOperatorIsUsed(): void
    {
        $this->rule->assign([
            'manufacturerIds' => ['1', '2'],
            'operator' => Rule::OPERATOR_LT,
        ]);

        $this->expectExceptionObject(RuleException::unsupportedOperator(Rule::OPERATOR_LT, RuleComparison::class));

        $this->rule->match(new LineItemScope(
            $this->createLineItemWithManufacturer('3'),
            $this->createMock(SalesChannelContext::class)
        ));
    }

    private function createLineItemWithManufacturer(string $manufacturerId): LineItem
    {
        return $this->createLineItem()->setPayloadValue('manufacturerId', $manufacturerId);
    }
}

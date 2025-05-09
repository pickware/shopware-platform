<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTaxationRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemTaxationRule::class)]
#[Group('rules')]
class LineItemTaxationRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemTaxationRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemTaxationRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemTaxation', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
        static::assertArrayHasKey('taxIds', $ruleConstraints, 'Rule Constraint taxIds is not defined');
    }

    /**
     * @param array<string> $taxIds
     */
    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesCorrectWithLineItemScope(
        array $taxIds,
        string $operator,
        string $lineItemTaxId,
        bool $expected
    ): void {
        $this->rule->assign([
            'taxIds' => $taxIds,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithTaxId($lineItemTaxId),
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
            'single product / equal / match tax id' => [['1', '2'], Rule::OPERATOR_EQ, '1', true],
            'single product / equal / no match' => [['1', '2'], Rule::OPERATOR_EQ, '3', false],
            'single product / not equal / match tax id' => [['1', '2'], Rule::OPERATOR_NEQ, '3', true],
        ];
    }

    /**
     * @param array<string> $taxIds
     */
    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        array $taxIds,
        string $operator,
        string $lineItemTaxId,
        bool $expected
    ): void {
        $this->rule->assign([
            'taxIds' => $taxIds,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithTaxId('1'),
            $this->createLineItemWithTaxId($lineItemTaxId),
        ]);
        $cart = $this->createCart($lineItemCollection);

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
            'multiple products / equal / match tax id' => [['1', '2'], Rule::OPERATOR_EQ, '2', true],
            'multiple products / equal / no match' => [['4', '5'], Rule::OPERATOR_EQ, '2', false],
            'multiple products / not equal / match tax id' => [['5', '6'], Rule::OPERATOR_NEQ, '2', true],
            'multiple products / not equal / no match tax id' => [['1', '2'], Rule::OPERATOR_NEQ, '2', false],
        ];
    }

    public function testIfMatchesCorrectWithCartRuleScopeNested(): void
    {
        $this->rule->assign([
            'taxIds' => ['1', '2'],
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithTaxId('1'),
            $this->createLineItemWithTaxId('2'),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testNotAvailableOperatorIsUsed(): void
    {
        $this->expectExceptionObject(RuleException::unsupportedOperator(Rule::OPERATOR_LT, RuleComparison::class));

        $this->rule->assign([
            'taxIds' => ['1', '2'],
            'operator' => Rule::OPERATOR_LT,
        ]);

        $this->rule->match(new LineItemScope(
            $this->createLineItemWithTaxId('3'),
            $this->createMock(SalesChannelContext::class)
        ));
    }

    private function createLineItemWithTaxId(string $taxId): LineItem
    {
        return $this->createLineItem()->setPayloadValue('taxId', $taxId);
    }
}

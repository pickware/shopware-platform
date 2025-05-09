<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Cart\Tax;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class TaxCalculationTypeTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @param ItemBlueprint[] $items
     */
    #[DataProvider('calculationProvider')]
    public function testCalculation(
        array $items,
        CalculatedTaxCollection $horizontal,
        CalculatedTaxCollection $vertical,
        bool $useNet = false
    ): void {
        $context = static::getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $taxState = $useNet ? CartPrice::TAX_STATE_NET : CartPrice::TAX_STATE_GROSS;
        $context->setTaxState($taxState);

        $calculator = static::getContainer()->get(AmountCalculator::class);
        $cart = $this->createCart($items, $context);

        $context->getSalesChannel()->setTaxCalculationType(SalesChannelDefinition::CALCULATION_TYPE_HORIZONTAL);
        $amount = $calculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );
        static::assertEquals($horizontal, $amount->getCalculatedTaxes());

        $context->getSalesChannel()->setTaxCalculationType(SalesChannelDefinition::CALCULATION_TYPE_VERTICAL);
        $amount = $calculator->calculate(
            $cart->getLineItems()->getPrices(),
            $cart->getDeliveries()->getShippingCosts(),
            $context
        );
        static::assertEquals($vertical, $amount->getCalculatedTaxes());
    }

    /**
     * @return array<array{0: ItemBlueprint[], 1: CalculatedTaxCollection, 2: CalculatedTaxCollection}>
     */
    public static function calculationProvider()
    {
        return [
            [
                [
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(0.69, 19, 4.29)]),
                new CalculatedTaxCollection([new CalculatedTax(0.68, 19, 4.29)]),
            ],
            [
                [
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(9.57, 19, 59.97)]),
                new CalculatedTaxCollection([new CalculatedTax(9.58, 19, 59.97)]),
            ],
            [
                [
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 7),
                    new ItemBlueprint(19.99, 1, 7),
                    new ItemBlueprint(19.99, 1, 7),
                ],
                new CalculatedTaxCollection([
                    new CalculatedTax(9.57, 19, 59.97),
                    new CalculatedTax(3.93, 7, 59.97),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(9.58, 19, 59.97),
                    new CalculatedTax(3.92, 7, 59.97),
                ]),
            ],

            // net calculations
            [
                [
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(0.81, 19, 4.29)]),
                new CalculatedTaxCollection([new CalculatedTax(0.82, 19, 4.29)]),
                true,
            ],
            // net calculation for different price
            [
                [
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                    new ItemBlueprint(19.99, 1, 19),
                ],
                new CalculatedTaxCollection([new CalculatedTax(11.4, 19, 59.97)]),
                new CalculatedTaxCollection([new CalculatedTax(11.39, 19, 59.97)]),
                true,
            ],

            // net calculation for tax mixed carts
            [
                [
                    new ItemBlueprint(13.31, 1, 7),
                    new ItemBlueprint(13.31, 1, 7),
                    new ItemBlueprint(13.31, 1, 7),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                    new ItemBlueprint(1.43, 1, 19),
                ],
                new CalculatedTaxCollection([
                    new CalculatedTax(2.79, 7, 39.93),
                    new CalculatedTax(0.81, 19, 4.29),
                ]),
                new CalculatedTaxCollection([
                    new CalculatedTax(2.80, 7, 39.93),
                    new CalculatedTax(0.82, 19, 4.29),
                ]),
                true,
            ],
        ];
    }

    /**
     * @param ItemBlueprint[] $items
     */
    private function createCart(array $items, SalesChannelContext $context): Cart
    {
        $cart = static::getContainer()
            ->get(CartService::class)
            ->getCart(Uuid::randomHex(), $context);

        foreach ($items as $i => $item) {
            $lineItem = new LineItem('item-' . $i, 'test', 'item-' . $i, $item->quantity);

            $definition = new QuantityPriceDefinition($item->price, new TaxRuleCollection([new TaxRule($item->taxRate)]), $item->quantity);

            $lineItem->setPriceDefinition($definition);

            $price = static::getContainer()
                ->get(QuantityPriceCalculator::class)
                ->calculate($definition, $context);

            $lineItem->setPrice($price);

            $cart->add($lineItem);
        }

        return $cart;
    }
}

/**
 * @internal
 */
#[Package('checkout')]
class ItemBlueprint
{
    public int $quantity;

    public float $price;

    public int $taxRate;

    public function __construct(
        float $price,
        int $quantity,
        int $taxRate
    ) {
        $this->quantity = $quantity;
        $this->price = $price;
        $this->taxRate = $taxRate;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceGrossRule;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group rules
 */
class LineItemPurchasePriceGrossRuleTest extends AbstractLineItemPurchasePriceRuleTest
{
    protected function setRule(): void
    {
        $this->rule = new LineItemPurchasePriceGrossRule();
    }

    protected function getTestName(): string
    {
        return 'cartLineItemPurchasePriceGross';
    }

    protected function createLineItem(float $purchasePriceGross): LineItem
    {
        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 3);
        $lineItem->setPurchasePrice(new PriceCollection([
            new Price(
                Defaults::CURRENCY,
                0,
                $purchasePriceGross,
                false
            ),
        ]));

        return $lineItem;
    }
}

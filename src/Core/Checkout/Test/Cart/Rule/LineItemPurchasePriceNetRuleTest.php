<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemPurchasePriceNetRule;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\PriceCollection;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @group rules
 */
class LineItemPurchasePriceNetRuleTest extends AbstractLineItemPurchasePriceRuleTest
{
    protected function setRule(): void
    {
        $this->rule = new LineItemPurchasePriceNetRule();
    }

    protected function getTestName(): string
    {
        return 'cartLineItemPurchasePriceNet';
    }

    protected function createLineItem(float $purchasePriceNet): LineItem
    {
        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, 3);
        $lineItem->setPurchasePrice(new PriceCollection([
            new Price(
                Defaults::CURRENCY,
                $purchasePriceNet,
                0,
                false
            ),
        ]));

        return $lineItem;
    }
}

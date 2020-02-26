<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Rule;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;

class LineItemPurchasePriceNetRule extends AbstractLineItemPurchasePriceRule
{
    public function getName(): string
    {
        return 'cartLineItemPurchasePriceNet';
    }

    protected function getPriceAmount(LineItem $lineItem, string $currencyId): ?float
    {
        if ($lineItem->getPurchasePrice()) {
            return $lineItem->getPurchasePrice()->getCurrencyPrice($currencyId)->getNet();
        }

        return null;
    }
}

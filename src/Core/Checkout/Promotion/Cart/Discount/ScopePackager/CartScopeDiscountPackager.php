<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\FilterableInterface;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class CartScopeDiscountPackager extends DiscountPackager
{
    public function getDecorated(): DiscountPackager
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * Gets all product line items of the entire cart that
     * match the rules and conditions of the provided discount item.
     */
    public function getMatchingItems(DiscountLineItem $discount, Cart $cart, SalesChannelContext $context): DiscountPackageCollection
    {
        $allItems = $cart->getLineItems()->filter(fn (LineItem $lineItem) => $lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE && $lineItem->isStackable());

        $priceDefinition = $discount->getPriceDefinition();
        if ($priceDefinition instanceof FilterableInterface && $priceDefinition->getFilter()) {
            $allItems = $allItems->filter(fn (LineItem $lineItem) => $priceDefinition->getFilter()->match(new LineItemScope($lineItem, $context)));
        }

        $discountPackage = $this->getDiscountPackage($allItems, $discount->isProductRestricted());
        if ($discountPackage === null) {
            return new DiscountPackageCollection([]);
        }

        return new DiscountPackageCollection([$discountPackage]);
    }

    private function getDiscountPackage(LineItemCollection $cartItems, bool $isAdvanceRuled): ?DiscountPackage
    {
        if (!Feature::isActive('PERFORMANCE_TWEAKS')) {
            $isAdvanceRuled = true;
        }

        $discountItems = [];

        foreach ($cartItems as $cartLineItem) {
            $item = new LineItemQuantity(
                $cartLineItem->getId(),
                $isAdvanceRuled ? 1 : $cartLineItem->getQuantity()
            );

            if ($isAdvanceRuled) {
                for ($i = 1; $i <= $cartLineItem->getQuantity(); ++$i) {
                    $discountItems[] = clone $item;
                }
            } else {
                $discountItems[] = $item;
            }
        }

        if (\count($discountItems) === 0) {
            return null;
        }

        // assign instead of add for performance reasons
        $collection = new LineItemQuantityCollection();
        $collection->assign(['elements' => $discountItems]);

        return new DiscountPackage($collection);
    }
}

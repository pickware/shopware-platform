<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Facade\Traits\DiscountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsCountTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsGetTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsHasTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsIteratorTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\ItemsRemoveTrait;
use Shopware\Core\Checkout\Cart\Facade\Traits\SurchargeTrait;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * The ContainerFacade allows you to wrap multiple line-items inside a container line-item.
 *
 * @script-service cart_manipulation
 *
 * @final
 */
#[Package('checkout')]
class ContainerFacade extends ItemFacade implements \Countable
{
    use DiscountTrait;
    use ItemsCountTrait;
    use ItemsGetTrait;
    use ItemsHasTrait;
    use ItemsIteratorTrait;
    use ItemsRemoveTrait;
    use SurchargeTrait;

    /**
     * @internal
     */
    public function __construct(
        private LineItem $item,
        private ScriptPriceStubs $priceStubs,
        CartFacadeHelper $helper,
        SalesChannelContext $context
    ) {
        parent::__construct($item, $priceStubs, $helper, $context);

        // Setting the properties of ItemsGetTrait and ItemsIteratorTrait
        $this->helper = $helper;
        $this->context = $context;
    }

    /**
     * The `product()` method returns all products inside the current container for further manipulation.
     * Similar to the `children()` method, but the line-items are filtered, to only contain product line items.
     *
     * @return ProductsFacade A `ProductsFacade` containing all product line-items inside the current container as a collection.
     */
    public function products(): ProductsFacade
    {
        return new ProductsFacade($this->item->getChildren(), $this->priceStubs, $this->helper, $this->context);
    }

    /**
     * Use the `add()` method to add an item to this container.
     *
     * @param ItemFacade $item The item that should be added.
     *
     * @return ItemFacade The item that was added to the container.
     *
     * @example add-container/add-container.twig 12 1 Add a product to the container and reduce the quantity of the original line-item.
     */
    public function add(ItemFacade $item): ItemFacade
    {
        $this->item->getChildren()->add($item->getItem());

        return $item;
    }

    protected function getItems(): LineItemCollection
    {
        // switch items pointer to children. Used for Items*Traits and DiscountTrait
        return $this->item->getChildren();
    }
}

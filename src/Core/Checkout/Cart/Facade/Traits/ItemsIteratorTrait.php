<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade\Traits;

use Shopware\Core\Checkout\Cart\Facade\CartFacadeHelper;
use Shopware\Core\Checkout\Cart\Facade\ContainerFacade;
use Shopware\Core\Checkout\Cart\Facade\ItemFacade;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
trait ItemsIteratorTrait
{
    private CartFacadeHelper $helper;

    private LineItemCollection $items;

    private SalesChannelContext $context;

    /**
     * @internal should not be used directly, loop over an ItemsFacade directly inside twig instead
     *
     * @return \ArrayIterator<array-key, ItemFacade|ContainerFacade>
     */
    public function getIterator(): \ArrayIterator
    {
        $items = [];
        foreach ($this->getItems() as $key => $item) {
            $items[$key] = match ($item->getType()) {
                LineItem::CONTAINER_LINE_ITEM => new ContainerFacade($item, $this->priceStubs, $this->helper, $this->context),
                default => new ItemFacade($item, $this->priceStubs, $this->helper, $this->context),
            };
        }

        /**
         * We need to force the type here, as `ContainerFacade` extends `ItemFacade`, so `ItemFacade|ContainerFacade` is normalized to `ItemFacade`.
         * See https://github.com/phpstan/phpstan/discussions/12727
         *
         * @var \ArrayIterator<array-key, ItemFacade>
         */
        return new \ArrayIterator($items);
    }
}

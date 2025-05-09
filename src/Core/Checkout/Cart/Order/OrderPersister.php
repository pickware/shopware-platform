<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Order;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\CartSerializationCleaner;
use Shopware\Core\Checkout\Cart\Exception\InvalidCartException;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Checkout\Order\OrderException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
class OrderPersister implements OrderPersisterInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderCollection> $orderRepository
     */
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly OrderConverter $converter,
        private readonly CartSerializationCleaner $cartSerializationCleaner,
    ) {
    }

    /**
     * @throws CartException
     * @throws OrderException
     * @throws InvalidCartException
     * @throws InconsistentCriteriaIdsException
     */
    public function persist(Cart $cart, SalesChannelContext $context): string
    {
        if ($cart->getErrors()->blockOrder()) {
            throw CartException::invalidCart($cart->getErrors());
        }

        if (!$context->getCustomer()) {
            throw CartException::customerNotLoggedIn();
        }

        if ($cart->getLineItems()->count() <= 0) {
            throw CartException::cartEmpty();
        }

        // cleanup cart before converting it to an order
        $this->cartSerializationCleaner->cleanupCart($cart);

        $order = $this->converter->convertToOrder($cart, $context, new OrderConversionContext());

        $context->getContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($order): void {
            $this->orderRepository->create([$order], $context);
        });

        return $order['id'];
    }
}

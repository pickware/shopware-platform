<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Event;

use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

#[Package('after-sales')]
abstract class DocumentOrderEvent extends Event
{
    /**
     * @param array<string, DocumentGenerateOperation> $operations
     */
    public function __construct(
        private readonly OrderCollection $orders,
        private readonly Context $context,
        private readonly array $operations = []
    ) {
    }

    /**
     * @return array<string, DocumentGenerateOperation> $operations
     */
    public function getOperations(): array
    {
        return $this->operations;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getOrders(): OrderCollection
    {
        return $this->orders;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart;

use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Struct\Struct;

class PaymentRefundTransactionStruct extends Struct
{
    /**
     * @var OrderEntity
     */
    private $order;

    /**
     * @var float
     */
    private $amount;

    public function __construct(OrderEntity $order, float $amount)
    {
        $this->order = $order;
        $this->amount = $amount;
    }

    public function getOrder(): OrderEntity
    {
        return $this->order;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }
}

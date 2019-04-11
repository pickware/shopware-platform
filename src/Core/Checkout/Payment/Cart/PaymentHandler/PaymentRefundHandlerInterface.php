<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\PaymentHandler;

use Shopware\Core\Checkout\Payment\Cart\PaymentRefundTransactionStruct;
use Shopware\Core\Checkout\Payment\Exception\PaymentRefundException;
use Shopware\Core\Framework\Context;

interface PaymentRefundHandlerInterface
{
    /**
     * @throws PaymentRefundException
     */
    public function refund(PaymentRefundTransactionStruct $transaction, Context $context): void;
}

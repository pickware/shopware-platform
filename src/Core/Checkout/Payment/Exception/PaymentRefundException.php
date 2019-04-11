<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

abstract class PaymentRefundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderId;

    public function __construct(string $orderId, string $message, array $parameters = [])
    {
        $this->orderId = $orderId;

        parent::__construct($message, $parameters);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }
}

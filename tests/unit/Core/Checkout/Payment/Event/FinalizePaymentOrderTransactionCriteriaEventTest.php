<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Event\FinalizePaymentOrderTransactionCriteriaEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(FinalizePaymentOrderTransactionCriteriaEvent::class)]
#[Package('checkout')]
class FinalizePaymentOrderTransactionCriteriaEventTest extends TestCase
{
    public function testEvent(): void
    {
        $transactionId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $criteria = new Criteria();

        $event = new FinalizePaymentOrderTransactionCriteriaEvent($transactionId, $criteria, $context);

        static::assertSame($transactionId, $event->getOrderTransactionId());
        static::assertSame($criteria, $event->getCriteria());
        static::assertSame($context, $event->getContext());
    }
}

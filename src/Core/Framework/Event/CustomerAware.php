<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event;

use Shopware\Core\Framework\Log\Package;

#[Package('fundamentals@after-sales')]
#[IsFlowEventAware]
interface CustomerAware
{
    public const CUSTOMER_ID = 'customerId';

    public const CUSTOMER = 'customer';

    public function getCustomerId(): string;
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Rule;

use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\DaysSinceRule;
use Shopware\Core\Framework\Rule\RuleScope;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - Will be internal in v6.7.0
 */
#[Package('fundamentals@after-sales')]
class DaysSinceLastOrderRule extends DaysSinceRule
{
    final public const RULE_NAME = 'customerDaysSinceLastOrder';

    public int $count;

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return $scope->getSalesChannelContext()->getCustomer()?->getLastOrderDate();
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof CheckoutRuleScope;
    }
}

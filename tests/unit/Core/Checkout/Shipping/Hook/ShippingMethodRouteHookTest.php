<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Shipping\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Hook\ShippingMethodRouteHook;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ShippingMethodRouteHook::class)]
class ShippingMethodRouteHookTest extends TestCase
{
    public function testConstruct(): void
    {
        $hook = new ShippingMethodRouteHook(
            $collection = new ShippingMethodCollection(),
            $onlyAvailable = true,
            $salesChannelContext = Generator::generateSalesChannelContext()
        );

        static::assertSame($collection, $hook->getCollection());
        static::assertSame($onlyAvailable, $hook->isOnlyAvailable());
        static::assertSame($salesChannelContext, $hook->getSalesChannelContext());

        static::assertSame('shipping-method-route-request', $hook->getName());
    }
}

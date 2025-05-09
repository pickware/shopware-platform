<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Facade\StatesFacade;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(StatesFacade::class)]
#[Package('checkout')]
class StatesFacadeTest extends TestCase
{
    public function testPublicApi(): void
    {
        $cart = new Cart('test');

        $facade = new StatesFacade($cart);
        static::assertFalse($facade->has('foo'));

        $facade->add('foo');
        static::assertTrue($facade->has('foo'));
        static::assertSame(['foo'], $facade->get());

        $facade->remove('foo');
        static::assertFalse($facade->has('foo'));
    }
}

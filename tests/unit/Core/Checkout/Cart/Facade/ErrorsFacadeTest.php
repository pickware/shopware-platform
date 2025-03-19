<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Facade;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Facade\ErrorsFacade;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[CoversClass(ErrorsFacade::class)]
#[Package('checkout')]
class ErrorsFacadeTest extends TestCase
{
    public function testPublicApiAvailable(): void
    {
        $facade = new ErrorsFacade(new ErrorCollection());

        $facade->warning('warning');
        $facade->error('error');
        $facade->notice('notice');

        static::assertCount(3, iterator_to_array($facade, false));
        static::assertTrue($facade->has('warning'));
        static::assertTrue($facade->has('error'));
        static::assertTrue($facade->has('notice'));

        $facade->warning('duplicate');
        $facade->warning('duplicate');
        static::assertTrue($facade->has('duplicate'));
        static::assertCount(4, iterator_to_array($facade, false));
        $facade->remove('duplicate');
        static::assertFalse($facade->has('duplicate'));
        static::assertCount(3, iterator_to_array($facade, false));

        static::assertInstanceOf(Error::class, $facade->get('error'));
        static::assertSame(Error::LEVEL_ERROR, $facade->get('error')->getLevel());
        static::assertInstanceOf(Error::class, $facade->get('warning'));
        static::assertSame(Error::LEVEL_WARNING, $facade->get('warning')->getLevel());
        static::assertInstanceOf(Error::class, $facade->get('notice'));
        static::assertSame(Error::LEVEL_NOTICE, $facade->get('notice')->getLevel());
    }
}

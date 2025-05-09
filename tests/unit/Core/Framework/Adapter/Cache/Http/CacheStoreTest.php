<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStateValidator;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Routing\MaintenanceModeResolver;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\Cache\CacheItem;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CacheStore::class)]
class CacheStoreTest extends TestCase
{
    public function testGetLock(): void
    {
        $request = new Request();

        $cache = $this->createMock(TagAwareAdapterInterface::class);

        $cache->expects($this->once())->method('hasItem')->willReturn(false);

        $item = new CacheItem();

        $cache->expects($this->once())->method('getItem')->willReturn($item);

        $cache->expects($this->once())->method('save')->with($item);

        $store = new CacheStore(
            $cache,
            $this->createMock(CacheStateValidator::class),
            new EventDispatcher(),
            new HttpCacheKeyGenerator('test', new EventDispatcher(), []),
            $this->createMock(MaintenanceModeResolver::class),
            [],
            $this->createMock(CacheTagCollector::class)
        );

        $store->lock($request);

        static::assertTrue($item->get());

        $value = ReflectionHelper::getPropertyValue($item, 'expiry');

        static::assertEqualsWithDelta(time() + 3, $value, 1);
    }

    public function testWriteDoesNotWriteCacheIfCacheStateIsInvalid(): void
    {
        $request = new Request();
        $response = new Response();

        $cache = $this->createMock(TagAwareAdapterInterface::class);
        $cache->expects($this->never())->method('save');

        $stateValidator = $this->createMock(CacheStateValidator::class);
        $stateValidator->expects($this->once())->method('isValid')->with($request)->willReturn(false);

        $store = new CacheStore(
            $cache,
            $stateValidator,
            new EventDispatcher(),
            new HttpCacheKeyGenerator('test', new EventDispatcher(), []),
            $this->createMock(MaintenanceModeResolver::class),
            [],
            $this->createMock(CacheTagCollector::class)
        );

        $store->write($request, $response);
    }
}

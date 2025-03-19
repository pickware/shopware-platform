<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Cache\ReverseProxy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheStateSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Adapter\Cache\Http\CacheStore;
use Shopware\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\AbstractReverseProxyGateway;
use Shopware\Core\Framework\Adapter\Cache\ReverseProxy\ReverseProxyCache;
use Shopware\Storefront\Framework\Routing\RequestTransformer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ReverseProxyCache::class)]
class ReverseProxyCacheTest extends TestCase
{
    public function testFlushIsCalledInDestruct(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);

        $gateway->expects($this->once())->method('flush');

        $cache = new ReverseProxyCache(
            $gateway,
            [],
            new CacheTagCollector($this->createMock(RequestStack::class))
        );

        // this is the only way to call the destructor
        unset($cache);
    }

    public function testTagsFromResponseGetsMergedAndRemoved(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);

        $gateway
            ->expects($this->once())
            ->method('tag')
            ->with(['foo']);

        $cache = new ReverseProxyCache($gateway, [], new CacheTagCollector($this->createMock(RequestStack::class)));

        $response = new Response();
        $response->headers->set(CacheStore::TAG_HEADER, '["foo"]');

        $request = new Request();
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, 'test');
        $cache->write($request, $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    /**
     * The store is only used to track the cache tags and not to cache actual
     */
    public function testLookup(): void
    {
        $store = new ReverseProxyCache(
            $this->createMock(AbstractReverseProxyGateway::class),
            [],
            new CacheTagCollector($this->createMock(RequestStack::class))
        );

        static::assertNull($store->lookup(new Request()));
        static::assertFalse($store->isLocked(new Request()));
        static::assertTrue($store->lock(new Request()));
        static::assertTrue($store->unlock(new Request()));
        $store->cleanup();
    }

    public function testWriteAddsGlobalStates(): void
    {
        $store = new ReverseProxyCache(
            $this->createMock(AbstractReverseProxyGateway::class),
            [CacheStateSubscriber::STATE_LOGGED_IN],
            new CacheTagCollector($this->createMock(RequestStack::class))
        );

        $request = new Request();
        $request->attributes->set(RequestTransformer::ORIGINAL_REQUEST_URI, '/foo');
        $response = new Response();
        $store->write($request, $response);

        static::assertTrue($response->headers->has(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER));
        static::assertSame($response->headers->get(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER), CacheStateSubscriber::STATE_LOGGED_IN);
    }

    public function testPurge(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects($this->once())->method('ban')->with(['/foo']);
        $store = new ReverseProxyCache($gateway, [], new CacheTagCollector($this->createMock(RequestStack::class)));

        $store->purge('/foo');
    }

    public function testInvalidateWithoutOriginalUrl(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects($this->never())->method('ban');
        $store = new ReverseProxyCache($gateway, [], new CacheTagCollector($this->createMock(RequestStack::class)));
        $store->invalidate(new Request());
    }

    public function testTaggingOfRequest(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects($this->once())->method('tag')->with(['product-1', 'category-1'], '/');

        $collector = $this->createMock(CacheTagCollector::class);
        $collector->expects($this->once())->method('get')->willReturn(['product-1', 'category-1']);

        $store = new ReverseProxyCache($gateway, [], $collector);

        $request = new Request();
        $store->write($request, new Response());
    }

    public function testInvoke(): void
    {
        $gateway = $this->createMock(AbstractReverseProxyGateway::class);
        $gateway->expects($this->once())->method('invalidate')->with(['foo']);
        $store = new ReverseProxyCache($gateway, [], new CacheTagCollector($this->createMock(RequestStack::class)));
        $store(new InvalidateCacheEvent(['foo']));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Routing\NotFound;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Shopware\Storefront\Framework\Routing\Exception\ErrorRedirectRequestEvent;
use Shopware\Storefront\Framework\Routing\NotFound\NotFoundSubscriber;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(NotFoundSubscriber::class)]
class NotFoundSubscriberTest extends TestCase
{
    public function testDebugIsOnDoesNothing(): void
    {
        $subscriber = new NotFoundSubscriber(
            $this->createMock(HttpKernelInterface::class),
            $this->createMock(SalesChannelContextServiceInterface::class),
            true,
            $this->createMock(CacheInterface::class),
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            new EventDispatcher()
        );

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            new Request(),
            0,
            new \Exception()
        );
        $subscriber->onError($event);

        static::assertNull($event->getResponse());
    }

    public function testErrorHandled(): void
    {
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $httpKernel
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(new Request());

        $subscriber = new NotFoundSubscriber(
            $httpKernel,
            $this->createMock(SalesChannelContextServiceInterface::class),
            false,
            new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter()),
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            new EventDispatcher()
        );

        $request = new Request();

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            $request,
            0,
            new HttpException(Response::HTTP_NOT_FOUND)
        );
        $subscriber->onError($event);

        $response = $event->getResponse();

        static::assertInstanceOf(Response::class, $response);
    }

    public function testCookiesAreNotPersistedToNotFoundPages(): void
    {
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $response = new Response();
        $response->headers->setCookie(new Cookie('extension-cookie', '1'));
        $response->headers->setCookie(new Cookie('session-', '1'));
        $httpKernel
            ->expects($this->once())
            ->method('handle')
            ->willReturn($response);

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(new Request());

        $arrayAdapter = new ArrayAdapter();
        $subscriber = new NotFoundSubscriber(
            $httpKernel,
            $this->createMock(SalesChannelContextServiceInterface::class),
            false,
            new TagAwareAdapter($arrayAdapter, $arrayAdapter),
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            new EventDispatcher(),
            []
        );

        $request = new Request();

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            $request,
            0,
            new HttpException(Response::HTTP_NOT_FOUND)
        );

        $subscriber->onError($event);

        $writtenCaches = array_values($arrayAdapter->getValues());

        static::assertArrayHasKey(0, $writtenCaches);

        $cacheItem = unserialize($writtenCaches[0]);
        static::assertInstanceOf(Response::class, $cacheItem);

        $cookies = $cacheItem->headers->getCookies();
        static::assertCount(1, $cookies);

        static::assertSame('extension-cookie', $cookies[0]->getName());
    }

    public function testOtherExceptionsDoNotGetCached(): void
    {
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $httpKernel
            ->expects($this->once())
            ->method('handle')
            ->willReturn(new Response());

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(new Request());

        $subscriber = new NotFoundSubscriber(
            $httpKernel,
            $this->createMock(SalesChannelContextServiceInterface::class),
            false,
            new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter()),
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            new EventDispatcher()
        );

        $request = new Request();

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            $request,
            0,
            new \Exception()
        );
        $subscriber->onError($event);

        static::assertInstanceOf(Response::class, $event->getResponse());

        $subscriber->reset();
    }

    public function testErrorRedirectIsModifiableAndNotCaptchaValidated(): void
    {
        $httpKernel = $this->createMock(HttpKernelInterface::class);
        $httpKernel
            ->expects($this->once())
            ->method('handle')
            ->with(static::callback(function (Request $request) {
                return $request->attributes->get(PlatformRequest::ATTRIBUTE_CAPTCHA) === false;
            }))
            ->willReturn(new Response());

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack->method('getMainRequest')->willReturn(new Request());

        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(ErrorRedirectRequestEvent::class));

        $subscriber = new NotFoundSubscriber(
            $httpKernel,
            $this->createMock(SalesChannelContextServiceInterface::class),
            false,
            new TagAwareAdapter(new ArrayAdapter(), new ArrayAdapter()),
            $this->createMock(EntityCacheKeyGenerator::class),
            $this->createMock(CacheInvalidator::class),
            $eventDispatcher,
        );

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CAPTCHA, true);

        $event = new ExceptionEvent(
            $this->createMock(Kernel::class),
            $request,
            0,
            new \Exception()
        );
        $subscriber->onError($event);
        $subscriber->reset();
    }

    #[DataProvider('providerSystemConfigKeys')]
    public function testInvalidationHappensOnSystemConfigChange(string $key, bool $shouldInvalidate): void
    {
        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $cacheInvalidator
            ->expects($shouldInvalidate ? $this->once() : $this->never())
            ->method('invalidate');

        $subscriber = new NotFoundSubscriber(
            $this->createMock(HttpKernelInterface::class),
            $this->createMock(SalesChannelContextServiceInterface::class),
            true,
            $this->createMock(CacheInterface::class),
            $this->createMock(EntityCacheKeyGenerator::class),
            $cacheInvalidator,
            new EventDispatcher()
        );

        $subscriber->onSystemConfigChanged(new SystemConfigChangedEvent($key, 'foo', null));
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function providerSystemConfigKeys(): iterable
    {
        yield 'key matches' => [
            'core.basicInformation.http404Page',
            true,
        ];

        yield 'key not matches' => [
            'core.http404Page',
            false,
        ];
    }

    public function testSubscribedEvents(): void
    {
        static::assertArrayHasKey(SystemConfigChangedEvent::class, NotFoundSubscriber::getSubscribedEvents());

        static::assertArrayHasKey(KernelEvents::EXCEPTION, NotFoundSubscriber::getSubscribedEvents());
    }
}

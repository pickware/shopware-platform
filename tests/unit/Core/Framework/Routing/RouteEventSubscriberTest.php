<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RouteEventSubscriber;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Kernel;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
#[CoversClass(RouteEventSubscriber::class)]
class RouteEventSubscriberTest extends TestCase
{
    public function testRequestEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new RequestEvent($this->createMock(Kernel::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.request', $listener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->request($event);
    }

    public function testResponseEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new ResponseEvent($this->createMock(Kernel::class), $request, HttpKernelInterface::MAIN_REQUEST, new Response());

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.response', $listener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->response($event);
    }

    public function testRenderEvent(): void
    {
        if (!\class_exists(StorefrontRenderEvent::class)) {
            // storefront dependency not installed
            return;
        }

        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new StorefrontRenderEvent('', [], $request, $this->createMock(SalesChannelContext::class));

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.render', $listener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->render($event);
    }

    public function testRequestScopeEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront', 'api']);

        $event = new RequestEvent($this->createMock(Kernel::class), $request, HttpKernelInterface::MAIN_REQUEST);

        $storefrontListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $storefrontListener->expects($this->once())->method('__invoke');

        $apiListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $apiListener->expects($this->once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('storefront.scope.request', $storefrontListener);
        $dispatcher->addListener('api.scope.request', $apiListener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->request($event);
    }

    public function testControllerEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');

        $event = new ControllerEvent(
            $this->createMock(Kernel::class),
            [CallableClassFoo::class, 'test'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.controller', $listener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->controller($event);
    }

    public function testControllerScopeEvent(): void
    {
        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront', 'api']);

        $event = new ControllerEvent(
            $this->createMock(Kernel::class),
            [CallableClassFoo::class, 'test'],
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        $storefrontListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $storefrontListener->expects($this->once())->method('__invoke');

        $apiListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $apiListener->expects($this->once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('storefront.scope.controller', $storefrontListener);
        $dispatcher->addListener('api.scope.controller', $apiListener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->controller($event);
    }

    public function testRenderScopeEvent(): void
    {
        if (!\class_exists(StorefrontRenderEvent::class)) {
            // storefront dependency not installed
            return;
        }

        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront', 'api']);

        $event = new StorefrontRenderEvent('', [], $request, $this->createMock(SalesChannelContext::class));

        $storefrontListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $storefrontListener->expects($this->once())->method('__invoke');

        $apiListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $apiListener->expects($this->once())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('storefront.scope.render', $storefrontListener);
        $dispatcher->addListener('api.scope.render', $apiListener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->render($event);
    }

    public function testResponseScopeEvent(): void
    {
        // Note: The current implementation of RouteEventSubscriber::response()
        // does not dispatch scope events for responses
        // This test is added to document this behavior

        $request = new Request();
        $request->attributes->set('_route', 'frontend.home.page');
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront', 'api']);

        $event = new ResponseEvent(
            $this->createMock(Kernel::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response()
        );

        $routeListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $routeListener->expects($this->once())->method('__invoke');

        // These listeners should not be called as per current implementation
        $storefrontListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $storefrontListener->expects($this->never())->method('__invoke');

        $apiListener = $this->getMockBuilder(CallableClass::class)->getMock();
        $apiListener->expects($this->never())->method('__invoke');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('frontend.home.page.response', $routeListener);
        $dispatcher->addListener('storefront.response', $storefrontListener);
        $dispatcher->addListener('api.response', $apiListener);

        $subscriber = new RouteEventSubscriber($dispatcher);
        $subscriber->response($event);
    }
}

/**
 * @internal
 */
class CallableClassFoo
{
    public static function test(): void
    {
    }
}

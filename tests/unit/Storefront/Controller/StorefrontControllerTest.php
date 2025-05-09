<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Shopware\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Shopware\Core\Framework\Adapter\Twig\TemplateFinder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RequestTransformerInterface;
use Shopware\Core\Framework\Script\Execution\ScriptExecutor;
use Shopware\Core\Framework\Test\Script\Execution\TestHook;
use Shopware\Core\Framework\Test\TestSessionStorage;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Controller\Exception\StorefrontException;
use Shopware\Storefront\Controller\StorefrontController;
use Shopware\Storefront\Event\StorefrontRedirectEvent;
use Shopware\Storefront\Framework\Routing\Router;
use Shopware\Tests\Unit\Storefront\Controller\fixtures\TestStorefrontController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Controller\ControllerResolverInterface;
use Symfony\Component\HttpKernel\HttpKernel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Twig\Error\SyntaxError;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(StorefrontController::class)]
class StorefrontControllerTest extends TestCase
{
    private readonly TestStorefrontController $controller;

    protected function setUp(): void
    {
        $this->controller = new TestStorefrontController();
    }

    public function testRenderStorefront(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $request = new Request(
            attributes: [
                'sw-sales-channel-context' => $context,
                'sw-storefront-url' => 'foo',
            ],
        );

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('render')
            ->willReturn('<html lang="en">test</html>');

        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);
        $seoUrlReplacer
            ->expects($this->once())
            ->method('replace')
            ->with('<html lang="en">test</html>', 'foo', $context)
            ->willReturn('<html lang="en">test</html>');

        $mediaUrlHandler = $this->createMock(MediaUrlPlaceholderHandlerInterface::class);
        $mediaUrlHandler->method('replace')->willReturnArgument(0);

        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('test.html.twig')
            ->willReturn('test.html.twig');

        $container = new ContainerBuilder();
        $container->set('request_stack', $requestStack);
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set('twig', $twig);
        $container->set(TemplateFinder::class, $templateFinder);
        $container->set(SeoUrlPlaceholderHandlerInterface::class, $seoUrlReplacer);
        $container->set(MediaUrlPlaceholderHandlerInterface::class, $mediaUrlHandler);
        $container->set(SystemConfigService::class, $this->createMock(SystemConfigService::class));
        $container->set('twig', $twig);

        $this->controller->setContainer($container);

        $response = $this->controller->testRenderStorefront('test.html.twig');

        static::assertSame('<html lang="en">test</html>', $response->getContent());
        static::assertSame('text/html', $response->headers->get('Content-Type'));
    }

    public function testRenderStorefrontWithException(): void
    {
        $context = $this->createMock(SalesChannelContext::class);

        $request = new Request(
            attributes: [
                'sw-sales-channel-context' => $context,
                'sw-storefront-url' => 'foo',
            ],
        );

        $requestStack = $this->createMock(RequestStack::class);
        $requestStack
            ->expects($this->once())
            ->method('getCurrentRequest')
            ->willReturn($request);

        $exception = new SyntaxError('test');
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('render')
            ->willThrowException($exception);

        $seoUrlReplacer = $this->createMock(SeoUrlPlaceholderHandlerInterface::class);

        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('test.html.twig')
            ->willReturn('test.html.twig');

        $container = new ContainerBuilder();
        $container->set('request_stack', $requestStack);
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set('twig', $twig);
        $container->set(TemplateFinder::class, $templateFinder);
        $container->set(SeoUrlPlaceholderHandlerInterface::class, $seoUrlReplacer);
        $container->set(SystemConfigService::class, $this->createMock(SystemConfigService::class));
        $container->set('twig', $twig);

        $this->controller->setContainer($container);

        $this->expectException(StorefrontException::class);
        $this->controller->testRenderStorefront('test.html.twig');
    }

    public function testTrans(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('test', ['foo' => 'bar']);

        $container = new ContainerBuilder();
        $container->set('translator', $translator);

        $this->controller->setContainer($container);

        $this->controller->testTrans('test', ['foo' => 'bar']);
    }

    public function testCreateActionResponseWithRedirectTo(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/foo/generated');

        $request = new Request(
            [
                'redirectTo' => 'foo',
                'redirectParameters' => ['foo' => 'bar'],
            ]
        );

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));

        $this->controller->setContainer($container);

        $response = $this->controller->testCreateActionResponse($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/foo/generated', $response->getTargetUrl());
    }

    public function testCreateActionResponseWithEmptyRedirectToWillRedirectToHomePage(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/');

        $request = new Request(
            [
                'redirectTo' => '',
                'redirectParameters' => [],
            ]
        );

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));

        $this->controller->setContainer($container);

        $response = $this->controller->testCreateActionResponse($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());
    }

    public function testCreateActionResponseWithArrayRedirectToWillRedirectToHomePage(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('frontend.home.page', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            ->willReturn('/');

        $request = new Request(
            [
                'redirectTo' => ['some', 'thing'],
                'redirectParameters' => [],
            ]
        );

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));

        $this->controller->setContainer($container);

        $response = $this->controller->testCreateActionResponse($request);

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());
    }

    public function testCreateActionResponseWithForwardTo(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], Router::PATH_INFO)
            ->willReturn('/foo/generated');

        $requestContext = $this->createMock(RequestContext::class);
        $requestContext
            ->method('getMethod')
            ->willReturn('POST');

        $router
            ->method('getContext')
            ->willReturn($requestContext);

        $router
            ->method('match')
            ->with('/foo/generated')
            ->willReturn(['_controller' => 'test_controller']);

        $request = new Request(
            [
                'forwardTo' => 'foo',
                'forwardParameters' => ['foo' => 'bar'],
            ]
        );

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $controllerResolver = $this->createMock(ControllerResolverInterface::class);
        $controllerResolver
            ->method('getController')
            ->willReturn(fn () => new Response('<html lang="en">test</html>', Response::HTTP_PERMANENTLY_REDIRECT, ['Content-Type' => 'text/html']));

        $kernel = new HttpKernel(
            $this->createMock(EventDispatcherInterface::class),
            $controllerResolver,
            $requestStack,
        );

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('event_dispatcher', $this->createMock(EventDispatcherInterface::class));
        $container->set('request_stack', $requestStack);
        $container->set(RequestTransformerInterface::class, $this->createMock(RequestTransformerInterface::class));
        $container->set('http_kernel', $kernel);

        $this->controller->setContainer($container);

        $response = $this->controller->testCreateActionResponse($request);

        static::assertNotInstanceOf(RedirectResponse::class, $response);
        static::assertSame('<html lang="en">test</html>', $response->getContent());
        static::assertSame('text/html', $response->headers->get('Content-Type'));
    }

    public function testCreateActionResponseWithNeitherRedirectNorForwardTo(): void
    {
        $response = $this->controller->testCreateActionResponse(new Request());

        static::assertNotInstanceOf(RedirectResponse::class, $response);
        static::assertSame('', $response->getContent());
    }

    public function testForwardToRoute(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('foo', ['foo' => 'bar'], Router::PATH_INFO)
            ->willReturn('/foo/generated');

        $requestContext = $this->createMock(RequestContext::class);
        $requestContext
            ->method('getMethod')
            ->willReturn('POST');

        $router
            ->method('getContext')
            ->willReturn($requestContext);

        $router
            ->expects($this->once())
            ->method('match')
            ->with('/foo/generated')
            ->willReturn(['_controller' => 'test_controller']);

        $request = new Request(
            [
                'forwardTo' => 'foo',
                'forwardParameters' => ['foo' => 'bar'],
            ]
        );

        $stack = new RequestStack();
        $stack->push($request);

        $requestTransformer = $this->createMock(RequestTransformerInterface::class);
        $requestTransformer
            ->expects($this->once())
            ->method('extractInheritableAttributes')
            ->with($request)
            ->willReturn(['foo' => 'bar']);

        $kernel = $this->createMock(HttpKernel::class);
        $kernel
            ->expects($this->once())
            ->method('handle')
            ->with(static::callback(
                static function (Request $request): bool {
                    static::assertSame('bar', $request->attributes->get('foo'));
                    static::assertSame('test_controller', $request->attributes->get('_controller'));
                    static::assertSame(['foo' => 'bar'], $request->attributes->get('_route_params'));

                    return true;
                }
            ));

        $container = new ContainerBuilder();
        $container->set('router', $router);
        $container->set('request_stack', $stack);
        $container->set(RequestTransformerInterface::class, $requestTransformer);
        $container->set('http_kernel', $kernel);

        $this->controller->setContainer($container);
        $this->controller->testForwardToRoute('foo', ['foo' => 'bar'], ['foo' => 'bar']);
    }

    public function testDecodeParamJson(): void
    {
        $request = new Request(['foobar' => '{"foo": "bar", "bar": "baz"}']);
        $params = $this->controller->testDecodeParam($request, 'foobar');

        static::assertCount(2, $params);

        static::assertArrayHasKey('foo', $params);
        static::assertSame('bar', $params['foo']);

        static::assertArrayHasKey('bar', $params);
        static::assertSame('baz', $params['bar']);
    }

    public function testDecodeParamsEmpty(): void
    {
        $request = new Request();
        $params = $this->controller->testDecodeParam($request, 'foo');

        static::assertEmpty($params);
    }

    public function testDecodeParamsNumeric(): void
    {
        $request = new Request(['foobar' => 1]);
        $params = $this->controller->testDecodeParam($request, 'foobar');

        static::assertEmpty($params);
    }

    public function testDecodeParamsArray(): void
    {
        $request = new Request(['foo' => ['bar' => 'baz'], 'another_one' => ['test' => 'foo']]);
        $params = $this->controller->testDecodeParam($request, 'foo');

        static::assertCount(1, $params);

        static::assertArrayHasKey('bar', $params);
        static::assertSame('baz', $params['bar']);
    }

    public function testAddCartErrors(): void
    {
        $error = new GenericCartError(
            'generic_test_error',
            'test.error.message',
            ['test' => 'error'],
            Error::LEVEL_ERROR,
            true,
            true,
            true,
        );

        $cart = new Cart('foo');
        $cart->addErrors($error);

        $request = new Request();
        $session = new Session(new TestSessionStorage());

        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->expects($this->once())
            ->method('trans')
            ->with('checkout.test.error.message', ['%test%' => 'error'])
            ->willReturn('A very nasty error');

        $container = new ContainerBuilder();
        $container->set('request_stack', $stack);
        $container->set('translator', $translator);

        $this->controller->setContainer($container);
        $this->controller->testAddCartErrors($cart);

        $updatedRequest = $stack->getMainRequest();

        static::assertInstanceOf(Request::class, $updatedRequest);

        $flashBag = $updatedRequest->getSession()->getBag('flashes');

        static::assertInstanceOf(FlashBagInterface::class, $flashBag);

        $flashes = $flashBag->all();

        static::assertCount(1, $flashes);

        static::assertArrayHasKey('danger', $flashes);
        static::assertCount(1, $flashes['danger']);

        static::assertSame('A very nasty error', $flashes['danger'][0]);
    }

    public function testRenderView(): void
    {
        $templateFinder = $this->createMock(TemplateFinder::class);
        $templateFinder
            ->expects($this->once())
            ->method('find')
            ->with('test.html.twig')
            ->willReturn('storefront-view.html.twig');

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects($this->once())
            ->method('render')
            ->with('storefront-view.html.twig', ['foo' => 'bar'])
            ->willReturn('<html lang="en">test</html>');

        $container = new ContainerBuilder();
        $container->set(TemplateFinder::class, $templateFinder);
        $container->set('twig', $twig);

        $this->controller->setContainer($container);

        $response = $this->controller->testRenderView('test.html.twig', ['foo' => 'bar']);

        static::assertSame('<html lang="en">test</html>', $response);
    }

    public function testHook(): void
    {
        $hook = new TestHook('test', Context::createDefaultContext());

        $executor = $this->createMock(ScriptExecutor::class);
        $executor
            ->expects($this->once())
            ->method('execute')
            ->with($hook);

        $container = new ContainerBuilder();
        $container->set(ScriptExecutor::class, $executor);

        $this->controller->setContainer($container);

        $this->controller->testHook($hook);
    }

    public function testRedirectEvent(): void
    {
        $event = new StorefrontRedirectEvent('test_route', ['test' => 'param']);

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::equalTo($event));

        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('test_route', ['test' => 'param'])
            ->willReturn('http://localhost/test_route');

        $container = new ContainerBuilder();
        $container->set('event_dispatcher', $dispatcher);
        $container->set('router', $router);

        $this->controller->setContainer($container);
        $this->controller->testRedirectToRoute('test_route', ['test' => 'param']);
    }
}

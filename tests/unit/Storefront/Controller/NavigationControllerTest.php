<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\NavigationController;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Page\Navigation\NavigationPageLoaderInterface;
use Shopware\Storefront\Pagelet\Footer\FooterPagelet;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoadedHook;
use Shopware\Storefront\Pagelet\Footer\FooterPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Header\HeaderPagelet;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoadedHook;
use Shopware\Storefront\Pagelet\Header\HeaderPageletLoaderInterface;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(NavigationController::class)]
class NavigationControllerTest extends TestCase
{
    private NavigationPageLoaderInterface&MockObject $pageLoader;

    private MenuOffcanvasPageletLoaderInterface&MockObject $offCanvasLoader;

    private NavigationControllerTestClass $controller;

    private HeaderPageletLoaderInterface&MockObject $headerLoader;

    private FooterPageletLoaderInterface&MockObject $footerLoader;

    protected function setUp(): void
    {
        $this->pageLoader = $this->createMock(NavigationPageLoaderInterface::class);
        $this->offCanvasLoader = $this->createMock(MenuOffcanvasPageletLoaderInterface::class);
        $this->headerLoader = $this->createMock(HeaderPageletLoaderInterface::class);
        $this->footerLoader = $this->createMock(FooterPageletLoaderInterface::class);

        $this->controller = new NavigationControllerTestClass($this->pageLoader, $this->offCanvasLoader, $this->headerLoader, $this->footerLoader);
    }

    public function testHomeRendersStorefront(): void
    {
        $this->pageLoader->method('load')
            ->willReturn(new NavigationPage());

        $request = new Request();
        $context = Generator::generateSalesChannelContext();

        $this->controller->home($request, $context);
        static::assertSame('@Storefront/storefront/page/content/index.html.twig', $this->controller->renderStorefrontView);
    }

    public function testIndexRendersStorefront(): void
    {
        $this->pageLoader->method('load')
            ->willReturn(new NavigationPage());

        $request = new Request([
            'navigationId' => Uuid::randomHex(),
        ]);
        $context = Generator::generateSalesChannelContext();

        $this->controller->index($context, $request);
        static::assertSame('@Storefront/storefront/page/content/index.html.twig', $this->controller->renderStorefrontView);
    }

    public function testOffcanvasRendersStorefront(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();

        $response = $this->controller->offcanvas($request, $context);
        static::assertSame('noindex', $response->headers->get('x-robots-tag'));
        static::assertSame('@Storefront/storefront/layout/navigation/offcanvas/navigation-pagelet.html.twig', $this->controller->renderStorefrontView);
    }

    public function testHeaderRendersStorefront(): void
    {
        $request = new Request(['headerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateSalesChannelContext();
        $headerPagelet = new HeaderPagelet(new Tree(null, []), new LanguageCollection(), new CurrencyCollection(), new LanguageEntity(), new CurrencyEntity());

        $this->headerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($headerPagelet);

        $this->controller->header($request, $context);
        static::assertSame('@Storefront/storefront/layout/header.html.twig', $this->controller->renderStorefrontView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderStorefrontParameters['headerParameters']);

        static::assertInstanceOf(HeaderPageletLoadedHook::class, $this->controller->calledHook);
        static::assertSame($headerPagelet, $this->controller->calledHook->getPage());
    }

    public function testFooterRendersStorefront(): void
    {
        $request = new Request(['footerParameters' => ['foo' => 'bar']]);
        $context = Generator::generateSalesChannelContext();
        $footerPagelet = new FooterPagelet(null, new CategoryCollection(), new PaymentMethodCollection(), new ShippingMethodCollection());

        $this->footerLoader->expects($this->once())->method('load')->with($request, $context)->willReturn($footerPagelet);

        $this->controller->footer($request, $context);
        static::assertSame('@Storefront/storefront/layout/footer.html.twig', $this->controller->renderStorefrontView);
        static::assertSame(['foo' => 'bar'], $this->controller->renderStorefrontParameters['footerParameters']);

        static::assertInstanceOf(FooterPageletLoadedHook::class, $this->controller->calledHook);
        static::assertSame($footerPagelet, $this->controller->calledHook->getPage());
    }
}

/**
 * @internal
 */
class NavigationControllerTestClass extends NavigationController
{
    use StorefrontControllerMockTrait;
}

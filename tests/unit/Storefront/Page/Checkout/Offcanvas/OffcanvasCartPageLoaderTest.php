<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Offcanvas;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPage;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Offcanvas\OffcanvasCartPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Shopware\Storefront\Page\Page;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(OffcanvasCartPageLoader::class)]
class OffcanvasCartPageLoaderTest extends TestCase
{
    public function testOffcanvasCartPageReturned(): void
    {
        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn(new Page());

        $this->expectNotToPerformAssertions();

        $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new OffcanvasCartPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new OffcanvasCartPage();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $page = $this->createLoader(pageLoader: $pageLoader)->load(
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testShippingMethodsAreSetToPage(): void
    {
        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethodResponse = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                ShippingMethodDefinition::ENTITY_NAME,
                2,
                $shippingMethods,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->method('load')
            ->withAnyParameters()
            ->willReturn($shippingMethodResponse);

        $page = $this->createLoader(shippingMethodRoute: $shippingMethodRoute)->load(
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame($shippingMethods, $page->getShippingMethods());
    }

    public function testValidationEventIsDispatched(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcher::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(OffcanvasCartPageLoadedEvent::class));

        $this->createLoader(eventDispatcher: $eventDispatcher)->load(
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );
    }

    public function testOnlyAvailableFlagIsSet(): void
    {
        $request = new Request(['onlyAvailable' => true]);
        $context = Generator::generateSalesChannelContext();

        $shippingMethodRoute = $this->createMock(ShippingMethodRoute::class);
        $shippingMethodRoute
            ->expects($this->once())
            ->method('load')
            ->with($request, $context, static::equalTo(new Criteria()));

        $loader = $this->createLoader(shippingMethodRoute: $shippingMethodRoute);
        $loader->load(new Request(), $context);
    }

    private function createLoader(
        ?EventDispatcher $eventDispatcher = null,
        ?GenericPageLoader $pageLoader = null,
        ?ShippingMethodRoute $shippingMethodRoute = null,
    ): OffcanvasCartPageLoader {
        return new OffcanvasCartPageLoader(
            $eventDispatcher ?? $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $pageLoader ?? $this->createMock(GenericPageLoader::class),
            $shippingMethodRoute ?? $this->createMock(ShippingMethodRoute::class),
        );
    }
}

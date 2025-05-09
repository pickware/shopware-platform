<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\CmsPageEntity;
use Shopware\Core\Framework\Routing\Exception\InvalidRouteScopeException;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\RequestStackTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Page\Navigation\NavigationPage;
use Shopware\Storefront\Test\Controller\StorefrontControllerTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
class StorefrontRoutingTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;
    use RequestStackTestBehaviour;
    use SessionTestBehaviour;
    use StorefrontControllerTestBehaviour;

    public function testForwardFromAddPromotionToHomePage(): void
    {
        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            StorefrontRenderEvent::class,
            function (StorefrontRenderEvent $event): void {
                $skippedViews = [
                    '@Storefront/storefront/layout/header.html.twig',
                    '@Storefront/storefront/layout/footer.html.twig',
                ];
                if (\in_array($event->getView(), $skippedViews, true)) {
                    return;
                }

                $data = $event->getParameters();
                static::assertInstanceOf(NavigationPage::class, $data['page']);
                static::assertInstanceOf(CmsPageEntity::class, $data['page']->getCmsPage());
                static::assertSame('Default listing layout', $data['page']->getCmsPage()->getName());
            }
        );

        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'frontend.home.page',
            ])
        );

        static::assertSame(200, $response->getStatusCode());
    }

    public function testForwardFromAddPromotionToApiFails(): void
    {
        $response = $this->request(
            'POST',
            '/checkout/promotion/add',
            $this->tokenize('frontend.checkout.promotion.add', [
                'forwardTo' => 'api.action.user.user-recovery.hash',
            ])
        );

        static::assertSame(Response::HTTP_PRECONDITION_FAILED, $response->getStatusCode());
        static::assertIsString($response->getContent());
        static::assertStringContainsString(InvalidRouteScopeException::class, $response->getContent());
    }
}

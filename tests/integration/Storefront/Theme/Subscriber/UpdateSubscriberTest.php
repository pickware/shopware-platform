<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Test\TestCaseBase\SalesChannelFunctionalTestBehaviour;
use Shopware\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\Subscriber\UpdateSubscriber;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeService;

/**
 * @internal
 */
class UpdateSubscriberTest extends TestCase
{
    use SalesChannelFunctionalTestBehaviour;

    protected function setUp(): void
    {
        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM `theme`');
    }

    public function testCompilesAllThemes(): void
    {
        $themeService = $this->createMock(ThemeService::class);
        $themeLifecycleService = $this->createMock(ThemeLifecycleService::class);
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $context = Context::createDefaultContext();

        $themes = $this->setupThemes($context);

        $updateSubscriber = new UpdateSubscriber($themeService, $themeLifecycleService, $salesChannelRepository);
        $event = new UpdatePostFinishEvent(Context::createDefaultContext(), 'v6.2.0', 'v6.2.1');

        $themeLifecycleService->expects($this->once())->method('refreshThemes');
        $themeService->expects($this->atLeast(2))
            ->method('compileThemeById')
            ->willReturnCallback(function ($themeId, $c) use (&$themes, $context) {
                $this->assertEquals($context, $c);
                $compiledThemes = [];
                if (isset($themes['otherTheme']) && $themes['otherTheme']['id'] === $themeId) {
                    $compiledThemes[] = $themes['otherTheme']['id'];
                    unset($themes['otherTheme']);
                } elseif (isset($themes['parentTheme']) && $themes['parentTheme']['id'] === $themeId) {
                    $compiledThemes[] = $themes['parentTheme']['id'];
                    unset($themes['parentTheme']);
                    if (isset($themes['childTheme'])) {
                        $compiledThemes[] = $themes['childTheme']['id'];
                        unset($themes['childTheme']);
                    }
                } elseif (isset($themes['childTheme']) && $themes['childTheme']['id'] === $themeId) {
                    $compiledThemes[] = $themes['childTheme']['id'];
                    unset($themes['childTheme']);
                }

                unset($themes[$themeId]);

                return $compiledThemes;
            });

        $updateSubscriber->updateFinished($event);
        static::assertEmpty($themes, print_r($themes, true));
    }

    public function testThemesAreNotCompiledWithStateSkipAssetBuilding(): void
    {
        $themeService = $this->createMock(ThemeService::class);
        $themeLifecycleService = $this->createMock(ThemeLifecycleService::class);

        /** @var EntityRepository<SalesChannelCollection> $salesChannelRepository */
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');

        $context = Context::createDefaultContext();

        $this->setupThemes($context);

        $context->addState(PluginLifecycleService::STATE_SKIP_ASSET_BUILDING);

        $updateSubscriber = new UpdateSubscriber($themeService, $themeLifecycleService, $salesChannelRepository);
        $event = new UpdatePostFinishEvent($context, 'v6.2.0', 'v6.2.1');

        $themeLifecycleService->expects($this->never())->method('refreshThemes');
        $themeService->expects($this->never())->method('compileThemeById');

        $updateSubscriber->updateFinished($event);
    }

    /**
     * @return array<string, array{id: string, salesChannelId: string}>
     */
    private function setupThemes(Context $context): array
    {
        /** @var EntityRepository<ThemeCollection> $themeRepository */
        $themeRepository = static::getContainer()->get('theme.repository');
        $themeSalesChannelRepository = static::getContainer()->get('theme_sales_channel.repository');

        $parentThemeId = Uuid::randomHex();
        $otherThemeId = Uuid::randomHex();
        $childThemeId = Uuid::randomHex();
        $themes = [
            'parentTheme' => [
                'id' => $parentThemeId,
                'salesChannelId' => Uuid::randomHex(),
            ],
            'otherTheme' => [
                'id' => $otherThemeId,
                'salesChannelId' => Uuid::randomHex(),
            ],
            'childTheme' => [
                'id' => $childThemeId,
                'salesChannelId' => Uuid::randomHex(),
            ],
        ];

        $themeRepository->create(
            [
                [
                    'id' => $parentThemeId,
                    'name' => 'Parent theme',
                    'technicalName' => 'parentTheme',
                    'author' => 'test',
                    'active' => true,
                ],
                [
                    'id' => $childThemeId,
                    'parentThemeId' => $parentThemeId,
                    'name' => 'Child theme',
                    'author' => 'test',
                    'active' => true,
                ],
                [
                    'id' => $otherThemeId,
                    'name' => 'Other theme',
                    'technicalName' => 'otherTheme',
                    'author' => 'test',
                    'active' => true,
                ],
            ],
            $context
        );

        foreach ($themes as $theme) {
            $this->createSalesChannel([
                'id' => $theme['salesChannelId'], 'domains' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                        'url' => 'http://localhost/' . $theme['id'],
                    ],
                ],
            ]);

            $themeSalesChannelRepository->create(
                [
                    ['themeId' => $theme['id'], 'salesChannelId' => $theme['salesChannelId']],
                ],
                $context
            );
        }

        return $themes;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\CategoryEvents;
use Shopware\Core\Content\Category\SalesChannel\SalesChannelCategoryEntity;
use Shopware\Core\Content\Category\Service\CategoryUrlGenerator;
use Shopware\Core\Content\Category\Subscriber\CategorySubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelEntityLoadedEvent;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;

/**
 * @internal
 */
#[CoversClass(CategorySubscriber::class)]
class CategorySubscriberTest extends TestCase
{
    public function testHasEvents(): void
    {
        $expectedEvents = [
            CategoryEvents::CATEGORY_LOADED_EVENT => 'entityLoaded',
            'sales_channel.' . CategoryEvents::CATEGORY_LOADED_EVENT => 'entityLoaded',
        ];

        static::assertSame($expectedEvents, CategorySubscriber::getSubscribedEvents());
    }

    #[DataProvider('entityLoadedEventDataProvider')]
    public function testEntityLoadedEvent(
        SystemConfigService $systemConfigService,
        CategoryEntity $categoryEntity,
        ?string $cmsPageIdBeforeEvent,
        ?string $cmsPageIdAfterEvent,
        ?string $salesChannelId
    ): void {
        $categorySubscriber = new CategorySubscriber($systemConfigService, $this->createMock(CategoryUrlGenerator::class));

        if ($salesChannelId) {
            $event = new SalesChannelEntityLoadedEvent(new CategoryDefinition(), [$categoryEntity], $this->getSalesChannelContext($salesChannelId));
        } else {
            $event = new EntityLoadedEvent(new CategoryDefinition(), [$categoryEntity], Context::createDefaultContext());
        }

        static::assertSame($cmsPageIdBeforeEvent, $categoryEntity->getCmsPageId());
        $categorySubscriber->entityLoaded($event);
        static::assertSame($cmsPageIdAfterEvent, $categoryEntity->getCmsPageId());
    }

    /**
     * @return array<string, array{SystemConfigService, CategoryEntity, string|null, string|null, string|null}>
     */
    public static function entityLoadedEventDataProvider(): iterable
    {
        yield 'It does not set cms page id if already set by the user' => [
            self::getSystemConfigServiceMock(),
            self::getCategory('foobar', false),
            'foobar',
            'foobar',
            null,
        ];

        yield 'It does not set cms page id if already set by the subscriber' => [
            self::getSystemConfigServiceMock(null, 'cmsPageId'),
            self::getCategory('differentCmsPageId', true, true),
            'differentCmsPageId',
            'differentCmsPageId',
            'salesChannelId',
        ];

        yield 'It does not set if no default is given' => [
            self::getSystemConfigServiceMock(),
            self::getCategory(null, false),
            null,
            null,
            null,
        ];

        yield 'It uses overall default if no salesChannel is given' => [
            self::getSystemConfigServiceMock(null, 'cmsPageId'),
            self::getCategory(null, false),
            null,
            'cmsPageId',
            null,
        ];

        yield 'It uses salesChannel specific default' => [
            self::getSystemConfigServiceMock('salesChannelId', 'salesChannelSpecificDefault'),
            self::getCategory(null, false, true),
            null,
            'salesChannelSpecificDefault',
            'salesChannelId',
        ];
    }

    private static function getSystemConfigServiceMock(?string $salesChannelId = null, ?string $cmsPageId = null): SystemConfigService
    {
        if ($salesChannelId === null && $cmsPageId === null) {
            return new StaticSystemConfigService([]);
        }

        if ($salesChannelId === null) {
            return new StaticSystemConfigService([
                CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $cmsPageId,
            ]);
        }

        return new StaticSystemConfigService([
            $salesChannelId => [
                CategoryDefinition::CONFIG_KEY_DEFAULT_CMS_PAGE_CATEGORY => $cmsPageId,
            ],
        ]);
    }

    private static function getCategory(?string $cmsPageId, bool $cmsPageIdSwitched, bool $salesChannelEntity = false): CategoryEntity
    {
        $category = new CategoryEntity();
        if ($salesChannelEntity) {
            $category = new SalesChannelCategoryEntity();
        }

        if ($cmsPageId) {
            $category->setCmsPageId($cmsPageId);
        }

        $category->setCmsPageIdSwitched($cmsPageIdSwitched);

        return $category;
    }

    private function getSalesChannelContext(string $salesChanelId): SalesChannelContext
    {
        $salesChannelEntity = new SalesChannelEntity();
        $salesChannelEntity->setId($salesChanelId);

        return Generator::generateSalesChannelContext(salesChannel: $salesChannelEntity);
    }
}

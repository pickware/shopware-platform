<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\File\FileNameProvider;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Kernel;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleCollection;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationCollection;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationEntity;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeEntity;
use Shopware\Storefront\Theme\ThemeFilesystemResolver;
use Shopware\Storefront\Theme\ThemeLifecycleService;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\ThemeWithFileAssociations\ThemeWithFileAssociations;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\ThemeWithLabels\ThemeWithLabels;

/**
 * @internal
 */
class ThemeLifecycleServiceTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ThemeLifecycleService $themeLifecycleService;

    private Context $context;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    private Connection $connection;

    private ThemeFilesystemResolver $themeFilesystemResolver;

    private ThemeRuntimeConfigService&MockObject $themeRuntimeConfigService;

    protected function setUp(): void
    {
        $kernel = $this->createMock(Kernel::class);
        $kernel->expects($this->any())->method('getBundles')->willReturn([
            'ThemeWithFileAssociations' => new ThemeWithFileAssociations(),
            'ThemeWithLabels' => new ThemeWithLabels(),
        ]);

        $kernel->expects($this->any())->method('getBundle')->willReturnMap([
            ['ThemeWithFileAssociations', new ThemeWithFileAssociations()],
            ['ThemeWithLabels', new ThemeWithLabels()],
        ]);

        $this->themeFilesystemResolver = new ThemeFilesystemResolver(
            static::getContainer()->get(SourceResolver::class),
            $kernel
        );
        $this->themeRepository = static::getContainer()->get('theme.repository');
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->connection = static::getContainer()->get(Connection::class);

        $this->themeRuntimeConfigService = $this->createMock(ThemeRuntimeConfigService::class);

        $this->themeLifecycleService = new ThemeLifecycleService(
            static::getContainer()->get(StorefrontPluginRegistry::class),
            $this->themeRepository,
            $this->mediaRepository,
            $this->mediaFolderRepository,
            static::getContainer()->get('theme_media.repository'),
            static::getContainer()->get(FileSaver::class),
            static::getContainer()->get(FileNameProvider::class),
            $this->themeFilesystemResolver,
            static::getContainer()->get('language.repository'),
            static::getContainer()->get('theme_child.repository'),
            $this->connection,
            static::getContainer()->get(StorefrontPluginConfigurationFactory::class),
            $this->themeRuntimeConfigService,
        );

        $this->context = Context::createDefaultContext();
    }

    public function testRefreshThemesCorrectConfigurationCollection(): void
    {
        $pluginRegistry = static::getContainer()->get(StorefrontPluginRegistry::class);
        $pluginConfigurationCollection = $pluginRegistry->getConfigurations();
        $bundle = $this->getThemeConfig();
        $themeConfigurations = new StorefrontPluginConfigurationCollection([$bundle]);

        foreach ($themeConfigurations as $themeConfiguration) {
            $this->themeRuntimeConfigService->expects($this->once())
                ->method('refreshRuntimeConfig')
                ->with(static::anything(), $themeConfiguration, $this->context, false, $pluginConfigurationCollection);
        }

        $this->themeLifecycleService->refreshThemes($this->context, $themeConfigurations);
    }

    public function testItRegistersANewThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(2, $themeEntity->getMedia());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeEntity->getMedia() as $media) {
            static::assertSame($themeDefaultFolderId, $media->getMediaFolderId());
        }
    }

    public function testThemeConfigInheritanceAddsParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);
        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testThemeRefreshWithParentTheme(): void
    {
        $parentBundle = $this->getThemeConfigWithLabels();
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);
        $bundle = $this->getThemeConfig();
        $bundle->setConfigInheritance(['@' . $parentBundle->getTechnicalName()]);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $parentThemeEntity = $this->getTheme($parentBundle);
        $themeEntity = $this->getTheme($bundle);

        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());

        $bundle->setConfigInheritance([]);
        $this->themeLifecycleService->refreshTheme($parentBundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertSame($parentThemeEntity->getId(), $themeEntity->getParentThemeId());
    }

    public function testYouCanUpdateConfigToAddNewMedia(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(3, $themeEntity->getMedia());
    }

    public function testItWontThrowIfMediaHasRestrictDeleteAssociation(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwareLogo = $this->getMedia('shopware_logo');
        $this->createCmsPage($shopwareLogo->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        // assert that the file shopware_logo was not deleted and is assigned to same media entity as before
        static::assertEquals($shopwareLogo, $this->getMedia('shopware_logo'));
    }

    public function testItDontRenamesThemeMediaIfItExistsBeforeAndIsSame(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToTheme($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwareLogoId = $this->getMedia('shopware_logo');
        $this->createCmsPage($shopwareLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $renamedShopwareLogoId = $this->getMedia('shopware_logo');
        static::assertNotNull($themeEntity->getMedia()->get($renamedShopwareLogoId->getId()));
    }

    public function testItRenamesThemeMediaIfItExistsBefore(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToThemeChanged($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwareLogoId = $this->getMedia('shopware_logo');
        $this->createCmsPage($shopwareLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $renamedShopwareLogoId = $this->getMedia('shopware_logo_pink2');
        static::assertNotNull($themeEntity->getMedia()->get($renamedShopwareLogoId->getId()));
    }

    public function testItIgnoresMediaFieldsWithoutValue(): void
    {
        $bundle = $this->getThemeConfig();
        $this->addPinkLogoToThemeWithoutValue($bundle);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $shopwareLogoId = $this->getMedia('shopware_logo');
        $this->createCmsPage($shopwareLogoId->getId());

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $this->hasNoMedia('shopware_logo_pink2');
    }

    public function testItUploadsFilesIntoTheRootFolderIfThemeDefaultFolderDoesNotExist(): void
    {
        $bundle = $this->getThemeConfig();
        $themeMediaDefaultFolderId = $this->getThemeMediaDefaultFolderId();

        $this->connection->executeStatement('
            UPDATE `media`
            SET `media_folder_id` = null
            WHERE `media_folder_id` = :defaultThemeFolder
        ', ['defaultThemeFolder' => Uuid::fromHexToBytes($themeMediaDefaultFolderId)]);
        $this->mediaFolderRepository->delete([['id' => $themeMediaDefaultFolderId]], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);

        static::assertTrue($themeEntity->isActive());
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        static::assertCount(2, $themeEntity->getMedia());

        foreach ($themeEntity->getMedia() as $media) {
            static::assertNull($media->getMediaFolderId());
        }
    }

    public function testItDoesNotOverridePreviewIfSetExclusive(): void
    {
        $previewMediaId = Uuid::randomHex();
        $this->mediaRepository->create([
            [
                'id' => $previewMediaId,
            ],
        ], $this->context);

        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        $this->themeRepository->update([
            [
                'id' => $theme->getId(),
                'previewMediaId' => $previewMediaId,
            ],
        ], $this->context);

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);
        static::assertSame($previewMediaId, $theme->getPreviewMediaId());
    }

    public function testItSkipsTranslationsIfLanguageIsNotAvailable(): void
    {
        $bundle = $this->getThemeConfigWithLabels();
        $this->deleteLanguageForLocale('de-DE');

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);

        static::assertInstanceOf(ThemeTranslationCollection::class, $theme->getTranslations());
        static::assertCount(1, $theme->getTranslations());
        $firstTranslation = $theme->getTranslations()->first();
        static::assertNotNull($firstTranslation);
        static::assertSame('en-GB', $firstTranslation->getLanguage()?->getLocale()?->getCode());
        static::assertSame(['fields.sw-image' => 'test label'], $firstTranslation->getLabels());
        static::assertSame(['fields.sw-image' => 'test help'], $firstTranslation->getHelpTexts());
    }

    public function testItUsesEnglishTranslationsAsFallbackIfDefaultLanguageIsNotProvided(): void
    {
        $bundle = $this->getThemeConfigWithLabels();
        $this->changeDefaultLanguageLocale('xx-XX');

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $theme = $this->getTheme($bundle);

        static::assertInstanceOf(ThemeTranslationCollection::class, $theme->getTranslations());
        static::assertCount(2, $theme->getTranslations());
        $translation = $this->getTranslationByLocale('xx-XX', $theme->getTranslations());
        static::assertSame([
            'fields.sw-image' => 'test label',
        ], $translation->getLabels());
        static::assertSame([
            'fields.sw-image' => 'test help',
        ], $translation->getHelpTexts());

        $germanTranslation = $this->getTranslationByLocale('de-DE', $theme->getTranslations());
        static::assertSame([
            'fields.sw-image' => 'Test label',
        ], $germanTranslation->getLabels());
        static::assertSame([
            'fields.sw-image' => 'Test Hilfe',
        ], $germanTranslation->getHelpTexts());
    }

    public function testItRemovesAThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle);
        static::assertInstanceOf(MediaCollection::class, $themeEntity->getMedia());
        $themeMedia = $themeEntity->getMedia();
        $ids = $themeMedia->getIds();

        static::assertTrue($themeEntity->isActive());
        static::assertCount(2, $themeMedia);

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeMedia as $media) {
            static::assertSame($themeDefaultFolderId, $media->getMediaFolderId());
        }

        $this->themeLifecycleService->removeTheme($bundle->getTechnicalName(), $this->context);

        // check whether the theme is no longer in the table and the associated media have been deleted
        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
    }

    public function testItRemovesAChildThemeCorrectly(): void
    {
        $bundle = $this->getThemeConfig();

        $this->themeLifecycleService->refreshTheme($bundle, $this->context);

        $themeEntity = $this->getTheme($bundle, true);
        $childId = Uuid::randomHex();

        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        // check if we have no dependent Themes
        static::assertCount(0, $themeEntity->getDependentThemes());

        // clone theme and make it child
        $this->themeRepository->clone($themeEntity->getId(), $this->context, $childId, new CloneBehavior([
            'technicalName' => null,
            'name' => 'Cloned theme',
            'parentThemeId' => $themeEntity->getId(),
        ]));

        // refresh theme to get child
        $themeEntity = $this->getTheme($bundle, true);

        $themeMedia = $themeEntity->getMedia();
        static::assertInstanceOf(MediaCollection::class, $themeMedia);
        $ids = $themeMedia->getIds();

        static::assertTrue($themeEntity->isActive());
        static::assertCount(2, $themeMedia);
        static::assertInstanceOf(ThemeCollection::class, $themeEntity->getDependentThemes());
        static::assertCount(1, $themeEntity->getDependentThemes());

        $themeDefaultFolderId = $this->getThemeMediaDefaultFolderId();
        foreach ($themeMedia as $media) {
            static::assertSame($themeDefaultFolderId, $media->getMediaFolderId());
        }

        $this->themeLifecycleService->removeTheme($bundle->getTechnicalName(), $this->context);

        // check whether the theme is no longer in the table and the associated media have been deleted
        static::assertFalse($this->hasTheme($bundle));
        static::assertCount(0, $this->mediaRepository->searchIds(new Criteria($ids), Context::createDefaultContext())->getIds());
        static::assertCount(0, $this->themeRepository->search(new Criteria([$childId, $themeEntity->getId()]), $this->context));
    }

    private function getThemeConfig(): StorefrontPluginConfiguration
    {
        $factory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithFileAssociations());
    }

    private function getThemeConfigWithLabels(): StorefrontPluginConfiguration
    {
        $factory = static::getContainer()->get(StorefrontPluginConfigurationFactory::class);

        return $factory->createFromBundle(new ThemeWithLabels());
    }

    private function getTheme(StorefrontPluginConfiguration $bundle, bool $withChild = false): ThemeEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));
        $criteria->addAssociation('media');
        $criteria->addAssociation('translations.language.locale');

        if ($withChild) {
            $criteria->addAssociation('dependentThemes');
        }

        $theme = $this->themeRepository->search($criteria, $this->context)->getEntities()->first();
        static::assertInstanceOf(ThemeEntity::class, $theme);

        return $theme;
    }

    private function hasTheme(StorefrontPluginConfiguration $bundle): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $bundle->getTechnicalName()));

        return $this->themeRepository->searchIds($criteria, $this->context)->getTotal() > 0;
    }

    private function getMedia(string $fileName): MediaEntity
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        $media = $this->mediaRepository->search($criteria, $this->context)->first();
        static::assertInstanceOf(MediaEntity::class, $media);

        return $media;
    }

    private function hasNoMedia(string $fileName): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('fileName', $fileName));

        $media = $this->mediaRepository->search($criteria, $this->context)->first();
        static::assertNull($media);
    }

    // we create a cms-page because it has has the DeleteRestricted flag in media definition
    private function createCmsPage(string $logoId): void
    {
        $manufacturerRepository = static::getContainer()->get('cms_page.repository');
        $manufacturerRepository->create([[
            'name' => 'dummy cms page',
            'previewMediaId' => $logoId,
            'type' => 'page',
            'config' => [],
        ]], $this->context);
    }

    private function addPinkLogoToTheme(StorefrontPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        $config['fields']['shopwareLogoPink'] = [
            'label' => [
                'en-GB' => 'shopware_logo_pink',
                'de-DE' => 'shopware_logo_pink',
            ],
            'type' => 'media',
            'value' => 'app/storefront/src/assets/image/shopware_logo_pink.svg',
        ];

        $bundle->setThemeConfig($config);
    }

    private function addPinkLogoToThemeChanged(StorefrontPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        $config['fields']['shopwareLogoPink'] = [
            'label' => [
                'en-GB' => 'shopware_logo_pink',
                'de-DE' => 'shopware_logo_pink',
            ],
            'type' => 'media',
            'value' => 'app/storefront/src/assets/image/shopware_logo_pink2.svg',
        ];

        $bundle->setThemeConfig($config);
    }

    private function addPinkLogoToThemeWithoutValue(StorefrontPluginConfiguration $bundle): void
    {
        $config = $bundle->getThemeConfig();
        $config['fields']['shopwareLogoPink'] = [
            'label' => [
                'en-GB' => 'shopware_logo_pink',
                'de-DE' => 'shopware_logo_pink',
            ],
            'type' => 'media',
        ];

        $bundle->setThemeConfig($config);
    }

    private function deleteLanguageForLocale(string $locale): void
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('translationCode.code', $locale));

        $id = $languageRepository->searchIds($criteria, $context)->firstId();

        $languageRepository->delete([
            ['id' => $id],
        ], $context);
    }

    private function changeDefaultLanguageLocale(string $locale): void
    {
        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('id', Defaults::LANGUAGE_SYSTEM));

        $language = $languageRepository->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($language);

        /** @var EntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = static::getContainer()->get('locale.repository');

        $localeRepository->upsert([
            [
                'id' => $language->getTranslationCodeId(),
                'code' => $locale,
            ],
        ], $context);
    }

    private function getTranslationByLocale(string $locale, ThemeTranslationCollection $translations): ThemeTranslationEntity
    {
        $entity = $translations->filter(static function (ThemeTranslationEntity $translation) use ($locale): bool {
            static::assertInstanceOf(LanguageEntity::class, $translation->getLanguage());
            static::assertInstanceOf(LocaleEntity::class, $translation->getLanguage()->getLocale());

            return $locale === $translation->getLanguage()->getLocale()->getCode();
        })->first();

        if ($entity === null) {
            throw new \RuntimeException('Translation not found.');
        }

        return $entity;
    }

    private function getThemeMediaDefaultFolderId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('media_folder.defaultFolder.entity', 'theme'));
        $criteria->addAssociation('defaultFolder');
        $criteria->setLimit(1);
        /** @var MediaFolderCollection $defaultFolder */
        $defaultFolder = $this->mediaFolderRepository->search($criteria, $this->context)->getEntities();

        if ($defaultFolder->count() !== 1 || $defaultFolder->first() === null) {
            throw new \RuntimeException('Default Theme folder does not exist.');
        }

        return $defaultFolder->first()->getId();
    }
}

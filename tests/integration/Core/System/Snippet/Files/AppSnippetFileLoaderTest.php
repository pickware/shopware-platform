<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\System\Snippet\Files;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Kernel;
use Shopware\Core\System\Snippet\Files\AppSnippetFileLoader;
use Shopware\Core\System\Snippet\Files\SnippetFileCollection;
use Shopware\Core\System\Snippet\Files\SnippetFileLoader;
use Shopware\Core\System\Snippet\Struct\TranslationConfig;
use Shopware\Core\Test\AppSystemTestBehaviour;

/**
 * @internal
 */
class AppSnippetFileLoaderTest extends TestCase
{
    use AppSystemTestBehaviour;
    use CacheTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private SnippetFileLoader $snippetFileLoader;

    protected function setUp(): void
    {
        $this->snippetFileLoader = new SnippetFileLoader(
            $this->createMock(Kernel::class),
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(AppSnippetFileLoader::class),
            static::getContainer()->get(ActiveAppsLoader::class),
            static::getContainer()->get(TranslationConfig::class),
        );
    }

    public function testLoadSnippetFilesIntoCollectionWithoutSnippetFiles(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithoutSnippets');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadSnippetFilesIntoCollection(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithSnippets');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/Apps/AppWithSnippets/Resources/snippet/storefront.de-DE.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('shopware AG', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/Apps/AppWithSnippets/Resources/snippet/storefront.en-GB.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('shopware AG', $snippetFile->getAuthor());
        static::assertFalse($snippetFile->isBase());
    }

    public function testLoadSnippetFilesDoesNotLoadSnippetsFromInactiveApps(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithSnippets', false);

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }

    public function testLoadBaseSnippetFilesIntoCollection(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/AppWithBaseSnippets');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(2, $collection);

        $snippetFile = $collection->getSnippetFilesByIso('de-DE')[0];
        static::assertSame('storefront.de-DE', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/Apps/AppWithBaseSnippets/Resources/snippet/storefront.de-DE.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('de-DE', $snippetFile->getIso());
        static::assertSame('shopware AG', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());

        $snippetFile = $collection->getSnippetFilesByIso('en-GB')[0];
        static::assertSame('storefront.en-GB', $snippetFile->getName());
        static::assertSame(
            __DIR__ . '/_fixtures/Apps/AppWithBaseSnippets/Resources/snippet/storefront.en-GB.base.json',
            $snippetFile->getPath()
        );
        static::assertSame('en-GB', $snippetFile->getIso());
        static::assertSame('shopware AG', $snippetFile->getAuthor());
        static::assertTrue($snippetFile->isBase());
    }

    public function testLoadSnippetFilesIntoCollectionIgnoresWrongFilenames(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/Apps/SnippetsWithWrongName');

        $collection = new SnippetFileCollection();

        $this->snippetFileLoader->loadSnippetFilesIntoCollection($collection);

        static::assertCount(0, $collection);
    }
}

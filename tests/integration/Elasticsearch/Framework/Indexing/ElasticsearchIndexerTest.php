<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class ElasticsearchIndexerTest extends TestCase
{
    use BasicTestDataBehaviour;
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    protected function setUp(): void
    {
        $this->clearElasticsearch();
    }

    protected function tearDown(): void
    {
        $this->clearElasticsearch();
    }

    public function testFirstIndexDoesNotCreateTask(): void
    {
        $c = static::getContainer()->get(Connection::class);
        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));

        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);
        $indexer->iterate(null);

        static::assertEmpty($c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task'));
    }

    public function testSecondIndexingCreatesTask(): void
    {
        $c = static::getContainer()->get(Connection::class);
        $before = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertEmpty($before);

        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        static::assertNotNull($indexer);

        $indexer->iterate(null);
        $indexer->iterate(null);

        $after = $c->fetchAllAssociative('SELECT * FROM elasticsearch_index_task');
        static::assertNotEmpty($after);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }
}

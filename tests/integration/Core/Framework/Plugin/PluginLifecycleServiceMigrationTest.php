<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin;

use Composer\IO\NullIO;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Composer\CommandExecutor;
use Shopware\Core\Framework\Plugin\KernelPluginCollection;
use Shopware\Core\Framework\Plugin\PluginCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\PluginLifecycleService;
use Shopware\Core\Framework\Plugin\PluginService;
use Shopware\Core\Framework\Plugin\Requirement\RequirementsValidator;
use Shopware\Core\Framework\Plugin\Util\AssetService;
use Shopware\Core\Framework\Plugin\Util\PluginFinder;
use Shopware\Core\Framework\Plugin\Util\VersionSanitizer;
use Shopware\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Shopware\Core\Framework\Test\Plugin\PluginTestsHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Kernel;
use Shopware\Core\System\CustomEntity\Schema\CustomEntityPersister;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
#[Group('slow')]
class PluginLifecycleServiceMigrationTest extends TestCase
{
    use KernelTestBehaviour;
    use MigrationTestBehaviour;
    use PluginTestsHelper;

    private ContainerInterface $container;

    /**
     * @var EntityRepository<PluginCollection>
     */
    private EntityRepository $pluginRepo;

    private PluginService $pluginService;

    private Connection $connection;

    private PluginLifecycleService $pluginLifecycleService;

    private Context $context;

    private string $fixturePath;

    public static function tearDownAfterClass(): void
    {
        $connection = Kernel::getConnection();

        $connection->executeStatement('DELETE FROM migration WHERE `class` LIKE "SwagManualMigrationTest%"');
        $connection->executeStatement('DELETE FROM plugin');

        KernelLifecycleManager::bootKernel();
    }

    protected function setUp(): void
    {
        // force kernel boot
        KernelLifecycleManager::bootKernel();

        $this->container = static::getContainer();
        $this->pluginRepo = $this->container->get('plugin.repository');
        $this->connection = $this->container->get(Connection::class);
        $this->pluginLifecycleService = $this->createPluginLifecycleService();
        $this->context = Context::createDefaultContext();

        $this->fixturePath = __DIR__ . '/../../../../../src/Core/Framework/Test/Plugin/_fixture/';

        $this->pluginService = $this->createPluginService(
            $this->fixturePath . 'plugins',
            $this->container->getParameter('kernel.project_dir'),
            $this->pluginRepo,
            $this->container->get('language.repository'),
            $this->container->get(PluginFinder::class)
        );

        $this->addTestPluginToKernel(
            $this->fixturePath . 'plugins/SwagManualMigrationTestPlugin',
            'SwagManualMigrationTestPlugin'
        );
        $this->requireMigrationFiles();

        $this->pluginService->refreshPlugins($this->context, new NullIO());
        $this->connection->executeStatement('DELETE FROM plugin WHERE `name` = "SwagTest"');
    }

    public function testInstall(): MigrationCollection
    {
        static::assertSame(0, $this->connection->getTransactionNestingLevel());

        $migrationPlugin = $this->getMigrationTestPlugin();
        static::assertNull($migrationPlugin->getInstalledAt());

        $this->pluginLifecycleService->installPlugin($migrationPlugin, $this->context);
        $migrationCollection = $this->getMigrationCollection('SwagManualMigrationTestPlugin');
        $this->assertMigrationState($migrationCollection, 4, 1);

        return $migrationCollection;
    }

    #[Depends('testInstall')]
    public function testActivate(MigrationCollection $migrationCollection): MigrationCollection
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->activatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 2);

        return $migrationCollection;
    }

    #[Depends('testActivate')]
    public function testUpdate(MigrationCollection $migrationCollection): MigrationCollection
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->updatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 3, 1);

        return $migrationCollection;
    }

    #[Depends('testUpdate')]
    public function testDeactivate(MigrationCollection $migrationCollection): MigrationCollection
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->deactivatePlugin($migrationPlugin, $this->context);
        $this->assertMigrationState($migrationCollection, 4, 3, 1);

        return $migrationCollection;
    }

    #[Depends('testDeactivate')]
    public function testUninstallKeepUserData(MigrationCollection $migrationCollection): void
    {
        $migrationPlugin = $this->getMigrationTestPlugin();
        $this->pluginLifecycleService->uninstallPlugin($migrationPlugin, $this->context, true);
        $this->assertMigrationCount($migrationCollection, 4);
    }

    private function assertMigrationCount(MigrationCollection $migrationCollection, int $expectedCount): void
    {
        $connection = static::getContainer()->get(Connection::class);

        /** @var MigrationSource $migrationSource */
        $migrationSource = ReflectionHelper::getPropertyValue($migrationCollection, 'migrationSource');

        $dbMigrations = $connection
            ->fetchAllAssociative(
                'SELECT * FROM `migration` WHERE `class` REGEXP :pattern ORDER BY `creation_timestamp`',
                ['pattern' => $migrationSource->getNamespacePattern()]
            );

        TestCase::assertCount($expectedCount, $dbMigrations);
    }

    private function createPluginLifecycleService(): PluginLifecycleService
    {
        return new PluginLifecycleService(
            $this->pluginRepo,
            $this->container->get('event_dispatcher'),
            $this->container->get(KernelPluginCollection::class),
            $this->container->get('service_container'),
            $this->container->get(MigrationCollectionLoader::class),
            $this->container->get(AssetService::class),
            $this->container->get(CommandExecutor::class),
            $this->container->get(RequirementsValidator::class),
            $this->container->get('cache.messenger.restart_workers_signal'),
            Kernel::SHOPWARE_FALLBACK_VERSION,
            $this->container->get(SystemConfigService::class),
            $this->container->get(CustomEntityPersister::class),
            $this->container->get(CustomEntitySchemaUpdater::class),
            $this->container->get(PluginService::class),
            $this->container->get(VersionSanitizer::class),
            $this->container->get(DefinitionInstanceRegistry::class),
        );
    }

    private function getMigrationTestPlugin(): PluginEntity
    {
        return $this->pluginService
            ->getPluginByName('SwagManualMigrationTestPlugin', $this->context);
    }

    private function requireMigrationFiles(): void
    {
        require_once $this->fixturePath . 'plugins/SwagManualMigrationTestPlugin/src/Migration/Migration1.php';
        require_once $this->fixturePath . 'plugins/SwagManualMigrationTestPlugin/src/Migration/Migration2.php';
        require_once $this->fixturePath . 'plugins/SwagManualMigrationTestPlugin/src/Migration/Migration3.php';
        require_once $this->fixturePath . 'plugins/SwagManualMigrationTestPlugin/src/Migration/Migration4.php';
    }
}

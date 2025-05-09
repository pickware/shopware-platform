<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Plugin\KernelPluginLoader;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\DbalKernelPluginLoader;
use Shopware\Core\Framework\Test\Plugin\PluginIntegrationTestBehaviour;

/**
 * @internal
 */
class DbalKernelPluginLoaderTest extends TestCase
{
    use PluginIntegrationTestBehaviour;

    public function testLoadNoPlugins(): void
    {
        $loader = new DbalKernelPluginLoader($this->classLoader, null, static::getContainer()->get(Connection::class));
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertEmpty($loader->getPluginInfos());
        static::assertEmpty($loader->getPluginInstances()->all());
    }

    public function testLoadNoInit(): void
    {
        $plugin = $this->getActivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, static::getContainer()->get(Connection::class));
        static::assertEmpty($loader->getPluginInfos());
    }

    public function testLoadPlugins(): void
    {
        $plugin = $this->getActivePlugin();
        $this->insertPlugin($plugin);

        $loader = new DbalKernelPluginLoader($this->classLoader, null, static::getContainer()->get(Connection::class));
        $loader->initializePlugins(TEST_PROJECT_DIR);

        static::assertNotEmpty($loader->getPluginInfos());
    }
}

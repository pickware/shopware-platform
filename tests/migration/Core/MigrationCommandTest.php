<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Framework\Migration\Command\MigrationCommand;
use Shopware\Core\Framework\Migration\Command\MigrationDestructiveCommand;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationException;
use Shopware\Core\Framework\Migration\MigrationRuntime;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Test\Migration\MigrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(MigrationCommand::class)]
class MigrationCommandTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MigrationTestBehaviour;

    protected function tearDown(): void
    {
        $connection = $this->getConnection();

        $connection->createQueryBuilder()
            ->delete('migration')
            ->where('`class` LIKE "%_test_migrations_valid%"')
            ->executeStatement();
    }

    public function getCommand(): MigrationCommand
    {
        return static::getContainer()->get(MigrationCommand::class);
    }

    public function getDestructiveCommand(): MigrationDestructiveCommand
    {
        return static::getContainer()->get(MigrationDestructiveCommand::class);
    }

    public function testCommandMigrateNoUntilNoAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('missing timestamp cap or --all option');
        $command->run(new ArrayInput([]), new BufferedOutput());
    }

    public function testCommandMigrateAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $command = $this->getCommand();

        $command->run(new ArrayInput(['-all' => true, 'identifier' => self::INTEGRATION_IDENTIFIER()]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testCommandMigrateMultipleIdentifiers(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $command->run(new ArrayInput(['identifier' => [self::INTEGRATION_IDENTIFIER(), '_test_migrations_valid_run_time'], '--all' => true]), new BufferedOutput());
        static::assertSame(4, $this->getMigrationCount(true));
    }

    public function testCommandMigrateMultipleIdentifiersSkipsNotFoundMigrationSources(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $command->run(new ArrayInput(['identifier' => ['noMigratioNSource', self::INTEGRATION_IDENTIFIER()], '--all' => true]), new BufferedOutput());
        static::assertSame(2, $this->getMigrationCount(true));
    }

    public function testCommandMigrateMultipleIdentifiersWithoutAllOptionThrowsException(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Running migrations for multiple identifiers without --all option or with --limit option is not supported.');
        $command->run(new ArrayInput(['identifier' => [self::INTEGRATION_IDENTIFIER(), '_test_migrations_valid_run_time'], '--until' => \PHP_INT_MAX]), new BufferedOutput());
    }

    public function testCommandMigrateMultipleIdentifiersWithLimitOptionThrowsException(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('Running migrations for multiple identifiers without --all option or with --limit option is not supported.');
        $command->run(new ArrayInput(['identifier' => [self::INTEGRATION_IDENTIFIER(), '_test_migrations_valid_run_time'], '--all' => true, '--limit' => 10]), new BufferedOutput());
    }

    public function testCommandAddMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $tester = new CommandTester($this->getCommand());

        $tester->execute(['identifier' => [self::INTEGRATION_IDENTIFIER()], '--until' => \PHP_INT_MAX]);

        // assert no deprecation notice is shown
        static::assertStringNotContainsString('v6.4.0', $tester->getDisplay());
        static::assertSame(2, $this->getMigrationCount());
    }

    public function testCommandMigrateMigrationException(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        try {
            $command->run(new ArrayInput(['--all' => true, 'identifier' => [self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER()]]), new BufferedOutput());
        } catch (MigrationException) {
            // nth
        }

        static::assertSame(3, $this->getMigrationCount(true));
    }

    public function testDestructiveCommandMigrateNoUntilNoAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getDestructiveCommand();

        $this->expectException(MigrationException::class);
        $this->expectExceptionMessage('missing timestamp cap or --all option');
        $command->run(new ArrayInput([]), new BufferedOutput());
    }

    public function testDestructiveCommandMigrateAllOption(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $command = $this->getDestructiveCommand();

        $command->run(new ArrayInput(['--all' => true, 'identifier' => [self::INTEGRATION_IDENTIFIER()]]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount());
    }

    public function testDestructiveCommandAddMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount());

        $tester = new CommandTester($this->getDestructiveCommand());

        $tester->execute(['identifier' => [self::INTEGRATION_IDENTIFIER()], '--until' => \PHP_INT_MAX]);

        // assert no deprecation notice is shown
        static::assertStringNotContainsString('v6.4.0', $tester->getDisplay());
        static::assertSame(2, $this->getMigrationCount());
    }

    public function testCommandMigrateMigrationDestructive(): void
    {
        static::assertSame(0, $this->getMigrationCount(true, true));

        $command = $this->getCommand();

        try {
            $command->run(new ArrayInput(['--all' => true, 'identifier' => [self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER()]]), new BufferedOutput());
        } catch (MigrationException) {
            // nth
        }

        $command = $this->getDestructiveCommand();

        try {
            $command->run(new ArrayInput(['--all' => true, 'identifier' => [self::INTEGRATION_WITH_EXCEPTION_IDENTIFIER()]]), new BufferedOutput());
        } catch (MigrationException) {
            // nth
        }

        static::assertSame(2, $this->getMigrationCount(true, true));
    }

    public function testCommandMigrate(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $command = $this->getCommand();

        $command->run(new ArrayInput(['--all' => true, 'identifier' => [self::INTEGRATION_IDENTIFIER()]]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount(true));
    }

    public function testCommandMigrateCacheClearBehaviourWithoutMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $connection = $this->getConnection();
        $loader = $this->getMockBuilder(MigrationCollectionLoader::class)->disableOriginalConstructor()->getMock();

        $nullLogger = new NullLogger();
        $loader->expects($this->once())->method('collect')->willReturn(
            new MigrationCollection(
                new MigrationSource(''),
                new MigrationRuntime($connection, $nullLogger),
                $connection,
                $nullLogger,
            )
        );

        $cache = $this->getMockBuilder(TagAwareAdapter::class)->disableOriginalConstructor()->getMock();
        $cache->expects($this->never())->method('clear');

        $command = new MigrationCommand($loader, $cache, static::getContainer()->getParameter('kernel.shopware_version'));

        $command->run(new ArrayInput(['--all' => true, 'identifier' => [self::INTEGRATION_IDENTIFIER()]]), new BufferedOutput());

        static::assertSame(0, $this->getMigrationCount(true));
    }

    public function testCommandMigrateCacheClearBehaviourWithOneMigration(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $cache = $this->getMockBuilder(TagAwareAdapter::class)->disableOriginalConstructor()->getMock();
        $cache->expects($this->once())->method('clear');

        $command = new MigrationCommand(static::getContainer()->get(MigrationCollectionLoader::class), $cache, static::getContainer()->getParameter('kernel.shopware_version'));

        $command->run(new ArrayInput(['--all' => true, '--limit' => 1, 'identifier' => [self::INTEGRATION_IDENTIFIER()]]), new BufferedOutput());

        static::assertSame(1, $this->getMigrationCount(true));
    }

    public function testCommandMigrateCacheClearBehaviourWithTwoMigrations(): void
    {
        static::assertSame(0, $this->getMigrationCount(true));

        $cache = $this->getMockBuilder(TagAwareAdapter::class)->disableOriginalConstructor()->getMock();
        $cache->expects($this->once())->method('clear');

        $command = new MigrationCommand(static::getContainer()->get(MigrationCollectionLoader::class), $cache, static::getContainer()->getParameter('kernel.shopware_version'));

        $command->run(new ArrayInput(['--all' => true, 'identifier' => [self::INTEGRATION_IDENTIFIER()]]), new BufferedOutput());

        static::assertSame(2, $this->getMigrationCount(true));
    }

    private function getConnection(): Connection
    {
        return static::getContainer()->get(Connection::class);
    }

    private function getMigrationCount(bool $executed = false, bool $destructive = false): int
    {
        $connection = $this->getConnection();

        $query = $connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('migration')
            ->where('`class` LIKE "%_test_migrations_valid%"');

        if ($executed && $destructive) {
            $query->andWhere('`update_destructive` IS NOT NULL');
        } elseif ($executed && !$destructive) {
            $query->andWhere('`update` IS NOT NULL');
        }

        return (int) $query->executeQuery()->fetchOne();
    }
}

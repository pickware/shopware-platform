<?php

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_6;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_6\Migration1728040726AddFlowExecutionTable;

/**
 * @internal
 */
#[Package('core')]
#[CoversClass(Migration1728040726AddFlowExecutionTable::class)]
class Migration1728040726AddFlowExecutionTableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testTableIsPresent(): void
    {
        $migration = new Migration1728040726AddFlowExecutionTable();
        $migration->update($this->connection);
        $migration->update($this->connection);
        $flowExecutionColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM flow_execution'), 'Field');

        static::assertContains('id', $flowExecutionColumns);
        static::assertContains('flow_id', $flowExecutionColumns);
        static::assertContains('event_data', $flowExecutionColumns);
        static::assertContains('successful', $flowExecutionColumns);
        static::assertContains('failed_flow_sequence_id', $flowExecutionColumns);
        static::assertContains('error_message', $flowExecutionColumns);
        static::assertContains('created_at', $flowExecutionColumns);
        static::assertContains('updated_at', $flowExecutionColumns);
    }
}

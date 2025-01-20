<?php

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_6;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1728040726AddFlowExecutionTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1728040726;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(
            <<<SQL
                CREATE TABLE IF NOT EXISTS `flow_execution` (
                    `id` binary(16) NOT NULL PRIMARY KEY,
                    `flow_id` binary(16) NOT NULL,
                    `event_data` JSON NOT NULl,
                    `successful` tinyint(1) NOT NULL,
                    `failed_flow_sequence_id` binary(16) DEFAULT NULL,
                    `error_message` text DEFAULT NULL,
                    `created_at` datetime(3) NOT NULL DEFAULT current_timestamp(3),
                    `updated_at` datetime(3) DEFAULT NULL,
                    CONSTRAINT `fk.flow_execution.flow_id`
                        FOREIGN KEY (`flow_id`)
                        REFERENCES `flow` (`id`)
                        ON DELETE CASCADE
                        ON UPDATE CASCADE,
                    CONSTRAINT `fk.flow_execution.failed_flow_sequence_id`
                        FOREIGN KEY (`failed_flow_sequence_id`)
                        REFERENCES `flow_sequence` (`id`)
                        ON DELETE SET NULL
                        ON UPDATE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                SQL,
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

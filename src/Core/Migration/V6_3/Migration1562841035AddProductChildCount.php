<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_3;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class Migration1562841035AddProductChildCount extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1562841035;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` ADD `child_count` INT(11)');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

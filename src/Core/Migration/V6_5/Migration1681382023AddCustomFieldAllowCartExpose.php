<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\AddColumnTrait;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1681382023AddCustomFieldAllowCartExpose extends MigrationStep
{
    use AddColumnTrait;

    public function getCreationTimestamp(): int
    {
        return 1681382023;
    }

    public function update(Connection $connection): void
    {
        $this->addColumn(
            $connection,
            'custom_field',
            'allow_cart_expose',
            'TINYINT(1)',
            false,
            '0',
        );

        $customFieldAllowList = $connection->fetchFirstColumn('SELECT JSON_UNQUOTE(JSON_EXTRACT(`value`, "$.renderedField.name")) as technical_name FROM rule_condition WHERE type = \'cartLineItemCustomField\';');

        foreach ($customFieldAllowList as $customField) {
            $connection->update(
                'custom_field',
                ['allow_cart_expose' => '1'],
                ['name' => $customField]
            );
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Doctrine\RetryableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Inherited;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class ManyToManyIdFieldUpdater
{
    /**
     * @internal
     */
    public function __construct(
        private readonly DefinitionInstanceRegistry $registry,
        private readonly Connection $connection
    ) {
    }

    /**
     * @param array<string> $ids
     */
    public function update(string $entity, array $ids, Context $context, ?string $propertyName = null): void
    {
        $definition = $this->registry->getByEntityName($entity);

        if (empty($ids)) {
            return;
        }

        $ids = array_unique($ids);

        if ($definition instanceof MappingEntityDefinition) {
            $fkFields = $definition->getFields()->filterInstance(FkField::class);

            /** @var FkField $field */
            foreach ($fkFields as $field) {
                $foreignKeys = array_column($ids, $field->getPropertyName());
                $this->update($field->getReferenceDefinition()->getEntityName(), $foreignKeys, $context);
            }

            return;
        }

        $fields = $definition->getFields()->filterInstance(ManyToManyIdField::class);

        if ($propertyName) {
            $fields = $fields->filter(fn (ManyToManyIdField $field) => $field->getPropertyName() === $propertyName);
        }

        if ($fields->count() <= 0) {
            return;
        }

        $template = <<<'SQL'
UPDATE #table#
SET #table#.#storage_name# = (
    SELECT CONCAT('[', GROUP_CONCAT(JSON_QUOTE(LOWER(HEX(#mapping_table#.#reference_column#)))), ']')
    FROM #mapping_table#
    WHERE #mapping_table#.#mapping_column# = #table#.#join_column#
    #mapping_version_aware#
)
WHERE #table#.id IN (:ids)
#table_version_aware#
SQL;

        $bytes = array_map(fn ($id) => Uuid::fromHexToBytes($id), $ids);

        /** @var ManyToManyIdField $field */
        foreach ($fields as $field) {
            /** @var ManyToManyAssociationField $association */
            $association = $definition->getFields()->get($field->getAssociationName());

            if (!$association instanceof ManyToManyAssociationField) {
                throw DataAbstractionLayerException::missingAssociation($definition->getEntityName(), $field->getAssociationName());
            }
            $parameters = ['ids' => $bytes];

            $replacement = [
                '#table#' => EntityDefinitionQueryHelper::escape($definition->getEntityName()),
                '#storage_name#' => EntityDefinitionQueryHelper::escape($field->getStorageName()),
                '#mapping_table#' => EntityDefinitionQueryHelper::escape($association->getMappingDefinition()->getEntityName()),
                '#reference_column#' => EntityDefinitionQueryHelper::escape($association->getMappingReferenceColumn()),
                '#mapping_column#' => EntityDefinitionQueryHelper::escape($association->getMappingLocalColumn()),
                '#join_column#' => EntityDefinitionQueryHelper::escape('id'),
            ];

            if ($definition->isInheritanceAware() && $association->is(Inherited::class)) {
                $replacement['#join_column#'] = EntityDefinitionQueryHelper::escape($association->getPropertyName());
            }

            $tableVersionCondition = '';
            $mappingVersionCondition = '';
            if ($definition->isVersionAware()) {
                $tableVersionCondition = 'AND #table#.version_id = :version';
                $mappingVersionCondition = 'AND #table#.version_id = #mapping_table#.#unescaped_table#_version_id';

                $parameters['version'] = Uuid::fromHexToBytes($context->getVersionId());
                $replacement['#unescaped_table#'] = $definition->getEntityName();
            }

            $tableTemplate = str_replace('#table_version_aware#', $tableVersionCondition, $template);
            $tableTemplate = str_replace('#mapping_version_aware#', $mappingVersionCondition, $tableTemplate);

            $sql = str_replace(
                array_keys($replacement),
                $replacement,
                $tableTemplate
            );

            RetryableQuery::retryable($this->connection, function () use ($sql, $parameters): void {
                $this->connection->executeStatement(
                    $sql,
                    $parameters,
                    ['ids' => ArrayParameterType::BINARY]
                );
            });
        }
    }
}

<?php

declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Execution;

use Shopware\Core\Content\Flow\Aggregate\FlowSequence\FlowSequenceDefinition;
use Shopware\Core\Content\Flow\FlowDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;

#[Package('services-settings')]
class FlowExecutionDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'flow_execution';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return FlowExecutionCollection::class;
    }

    public function getEntityClass(): string
    {
        return FlowExecutionEntity::class;
    }

    public function since(): ?string
    {
        return '6.6.9.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new BoolField('successful', 'successful'))->addFlags(new Required()),
            new StringField('error_message', 'errorMessage', 65535), // This is a TEXT field
            (new JsonField('event_data', 'eventData'))->addFlags(new Required()),
            (new FkField('flow_id', 'flowId', FlowDefinition::class, 'id'))->addFlags(new Required()),
            new ManyToOneAssociationField('flow', 'flow_id', FlowDefinition::class, 'id'),
            new FkField('failed_flow_sequence_id', 'failedFlowSequenceId', FlowSequenceDefinition::class, 'id'),
            new ManyToOneAssociationField('failedFlowSequence', 'failed_flow_sequence_id', FlowSequenceDefinition::class, 'id'),
        ]);
    }
}

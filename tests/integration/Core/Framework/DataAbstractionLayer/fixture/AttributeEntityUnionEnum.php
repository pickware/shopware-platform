<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\fixture;

use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\FieldType;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Entity as EntityStruct;

/**
 * @internal
 */
#[Entity('attribute_entity_union_enum', since: '6.6.10.0')]
class AttributeEntityUnionEnum extends EntityStruct
{
    #[PrimaryKey]
    #[Field(type: FieldType::UUID)]
    public string $id;

    #[Field(type: FieldType::ENUM)]
    /**
     * @phpstan-ignore property.unresolvableNativeType (we want to explicitly test this case)
     */
    public StringEnum&IntEnum $enum;
}

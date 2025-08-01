<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AutoIncrementField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BlobField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CashRoundingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildCountField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ConfigJsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CronIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateIntervalField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\EmailField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Runtime;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LockedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceDefinitionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\RemoteAddressField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TaxFreeConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeBreadcrumbField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreeLevelField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TreePathField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedByField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VariantListingConfigField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\NumberRange\DataAbstractionLayer\NumberRangeField;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(SchemaBuilder::class)]
class SchemaBuilderTest extends TestCase
{
    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new StaticDefinitionInstanceRegistry(
            [
                TestEntityWithSkippedFieldsDefinition::class,
                TestAssociationDefinition::class,
                TestEntityWithAllPossibleFieldsDefinition::class,
                TestEntityWithForeignKeysDefinition::class,
                ProductDefinition::class,
                TestAssociationWithMissingReferenceVersionDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
    }

    public function testSkipsCertainFields(): void
    {
        $definition = $this->registry->get(TestEntityWithSkippedFieldsDefinition::class);

        $table = (new SchemaBuilder())->buildSchemaOfDefinition($definition);

        static::assertCount(4, $table->getColumns());

        static::assertSame('id', $table->getPrimaryKeyConstraint()?->getColumnNames()[0]->toString());

        static::assertTrue($table->hasColumn('id'));
        static::assertTrue($table->hasColumn('relation_id'));

        static::assertFalse($table->hasColumn('runtime'));
        static::assertFalse($table->hasColumn('translated'));

        static::assertSame('utf8mb4', $table->getOption('charset'));
        static::assertSame('utf8mb4_unicode_ci', $table->getOption('collate'));

        $pks = $table->getPrimaryKeyConstraint()->getColumnNames();
        static::assertCount(1, $pks);
        $firstPk = $pks[0];
        static::assertSame('id', $firstPk->toString());
    }

    public function testDifferentFieldTypes(): void
    {
        $definition = $this->registry->get(TestEntityWithAllPossibleFieldsDefinition::class);

        $table = (new SchemaBuilder())->buildSchemaOfDefinition($definition);

        static::assertSame('id', $table->getPrimaryKeyConstraint()?->getColumnNames()[0]->toString());

        static::assertTrue($table->hasColumn('id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('id')->getType()));

        static::assertTrue($table->hasColumn('version_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('version_id')->getType()));

        static::assertTrue($table->hasColumn('created_by_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('created_by_id')->getType()));

        static::assertTrue($table->hasColumn('updated_by_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('updated_by_id')->getType()));

        static::assertTrue($table->hasColumn('state_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('state_id')->getType()));

        static::assertTrue($table->hasColumn('created_at'));
        static::assertSame(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumn('created_at')->getType()));

        static::assertTrue($table->hasColumn('updated_at'));
        static::assertSame(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumn('updated_at')->getType()));

        static::assertTrue($table->hasColumn('datetime'));
        static::assertSame(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumn('datetime')->getType()));

        static::assertTrue($table->hasColumn('date'));
        static::assertSame(Types::DATE_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumn('date')->getType()));

        static::assertTrue($table->hasColumn('cart_price'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('cart_price')->getType()));

        static::assertTrue($table->hasColumn('calculated_price'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('calculated_price')->getType()));

        static::assertTrue($table->hasColumn('price'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('price')->getType()));

        static::assertTrue($table->hasColumn('price_definition'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('price_definition')->getType()));

        static::assertTrue($table->hasColumn('json'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('json')->getType()));

        static::assertTrue($table->hasColumn('list'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('list')->getType()));

        static::assertTrue($table->hasColumn('config_json'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('config_json')->getType()));

        static::assertTrue($table->hasColumn('custom_fields'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('custom_fields')->getType()));

        static::assertTrue($table->hasColumn('breadcrumb'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('breadcrumb')->getType()));

        static::assertTrue($table->hasColumn('cash_rounding_config'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('cash_rounding_config')->getType()));

        static::assertTrue($table->hasColumn('object'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('object')->getType()));

        static::assertTrue($table->hasColumn('tax_free_config'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('tax_free_config')->getType()));

        static::assertTrue($table->hasColumn('tree_breadcrumb'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('tree_breadcrumb')->getType()));

        static::assertTrue($table->hasColumn('variant_listing_config'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('variant_listing_config')->getType()));

        static::assertTrue($table->hasColumn('version_data_payload'));
        static::assertSame(Types::JSON, Type::getTypeRegistry()->lookupName($table->getColumn('version_data_payload')->getType()));

        static::assertTrue($table->hasColumn('child_count'));
        static::assertSame(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumn('child_count')->getType()));

        static::assertTrue($table->hasColumn('auto_increment'));
        static::assertSame(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumn('auto_increment')->getType()));

        static::assertTrue($table->hasColumn('int'));
        static::assertSame(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumn('int')->getType()));

        static::assertTrue($table->hasColumn('auto_increment'));
        static::assertSame(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumn('auto_increment')->getType()));

        static::assertTrue($table->hasColumn('tree_level'));
        static::assertSame(Types::INTEGER, Type::getTypeRegistry()->lookupName($table->getColumn('tree_level')->getType()));

        static::assertTrue($table->hasColumn('bool'));
        static::assertSame(Types::BOOLEAN, Type::getTypeRegistry()->lookupName($table->getColumn('bool')->getType()));

        static::assertTrue($table->hasColumn('locked'));
        static::assertSame(Types::BOOLEAN, Type::getTypeRegistry()->lookupName($table->getColumn('locked')->getType()));

        static::assertTrue($table->hasColumn('password'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('password')->getType()));

        static::assertTrue($table->hasColumn('string'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('string')->getType()));

        static::assertTrue($table->hasColumn('timezone'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('timezone')->getType()));

        static::assertTrue($table->hasColumn('cron_interval'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('cron_interval')->getType()));

        static::assertTrue($table->hasColumn('date_interval'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('date_interval')->getType()));

        static::assertTrue($table->hasColumn('email'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('email')->getType()));

        static::assertTrue($table->hasColumn('remote_address'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('remote_address')->getType()));

        static::assertTrue($table->hasColumn('number_range'));
        static::assertSame(Types::STRING, Type::getTypeRegistry()->lookupName($table->getColumn('number_range')->getType()));

        static::assertTrue($table->hasColumn('blob'));
        static::assertSame(Types::BLOB, Type::getTypeRegistry()->lookupName($table->getColumn('blob')->getType()));

        static::assertTrue($table->hasColumn('float'));
        static::assertSame(Types::DECIMAL, Type::getTypeRegistry()->lookupName($table->getColumn('float')->getType()));

        static::assertTrue($table->hasColumn('tree_path'));
        static::assertSame(Types::TEXT, Type::getTypeRegistry()->lookupName($table->getColumn('tree_path')->getType()));

        static::assertTrue($table->hasColumn('long_text'));
        static::assertSame(Types::TEXT, Type::getTypeRegistry()->lookupName($table->getColumn('long_text')->getType()));
    }

    public function testForeignKeys(): void
    {
        $definition = $this->registry->get(TestEntityWithForeignKeysDefinition::class);

        $table = (new SchemaBuilder())->buildSchemaOfDefinition($definition);

        static::assertSame('id', $table->getPrimaryKeyConstraint()?->getColumnNames()[0]->toString());

        static::assertTrue($table->hasColumn('id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('id')->getType()));

        static::assertTrue($table->hasColumn('version_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('version_id')->getType()));

        static::assertTrue($table->hasColumn('parent_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('parent_id')->getType()));

        static::assertTrue($table->hasColumn('parent_version_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('parent_version_id')->getType()));

        static::assertTrue($table->hasColumn('created_at'));
        static::assertSame(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumn('created_at')->getType()));

        static::assertTrue($table->hasColumn('updated_at'));
        static::assertSame(Types::DATETIME_MUTABLE, Type::getTypeRegistry()->lookupName($table->getColumn('updated_at')->getType()));

        static::assertTrue($table->hasColumn('association_id'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('association_id')->getType()));

        static::assertTrue($table->hasColumn('association_id2'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('association_id2')->getType()));

        static::assertTrue($table->hasColumn('association_id3'));
        static::assertSame(Types::BINARY, Type::getTypeRegistry()->lookupName($table->getColumn('association_id3')->getType()));

        static::assertTrue($table->hasForeignKey('fk.test_entity_with_foreign_keys.association_id'));
        static::assertTrue($table->hasForeignKey('fk.test_entity_with_foreign_keys.association_id2'));
        static::assertTrue($table->hasForeignKey('fk.test_entity_with_foreign_keys.association_id3'));

        $associationFk = $table->getForeignKey('fk.test_entity_with_foreign_keys.association_id');

        static::assertSame('association_id', $associationFk->getReferencingColumnNames()[0]->toString());
        static::assertSame('test_association', $associationFk->getReferencedTableName()->toString());
        static::assertSame('id', $associationFk->getReferencedColumnNames()[0]->toString());
        static::assertSame('CASCADE', $associationFk->getOnUpdateAction()->value);
        static::assertSame('SET NULL', $associationFk->getOnDeleteAction()->value);

        $associationFk2 = $table->getForeignKey('fk.test_entity_with_foreign_keys.association_id2');

        static::assertSame('association_id2', $associationFk2->getReferencingColumnNames()[0]->toString());
        static::assertSame('test_association', $associationFk2->getReferencedTableName()->toString());
        static::assertSame('id', $associationFk2->getReferencedColumnNames()[0]->toString());
        static::assertSame('CASCADE', $associationFk2->getOnUpdateAction()->value);
        static::assertSame('CASCADE', $associationFk2->getOnDeleteAction()->value);

        $associationFk3 = $table->getForeignKey('fk.test_entity_with_foreign_keys.association_id3');

        static::assertSame('association_id3', $associationFk3->getReferencingColumnNames()[0]->toString());
        static::assertSame('test_association', $associationFk3->getReferencedTableName()->toString());
        static::assertSame('id', $associationFk3->getReferencedColumnNames()[0]->toString());
        static::assertSame('CASCADE', $associationFk3->getOnUpdateAction()->value);
        static::assertSame('RESTRICT', $associationFk3->getOnDeleteAction()->value);
    }

    public function testDefinitionMissingReferenceVersionField(): void
    {
        $definition = $this->registry->get(TestAssociationWithMissingReferenceVersionDefinition::class);

        $schemaBuilder = new SchemaBuilder();

        $this->expectExceptionObject(DataAbstractionLayerException::versionFieldNotFound('product'));
        $schemaBuilder->buildSchemaOfDefinition($definition);
    }
}

/**
 * @internal
 */
class TestEntityWithSkippedFieldsDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_with_skipped_fields';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new NonStorageAwareField('id2'))->addFlags(new PrimaryKey(), new Required()),
            (new StringField('runtime', 'runtime'))->addFlags(new Runtime()),
            new FkField('relation_id', 'relationId', TestAssociationDefinition::class),
            new ManyToOneAssociationField('relation', 'relation_id', TestAssociationDefinition::class, 'id'),
            new TranslatedField('translated'),
            new NonStorageAwareField('nonStorageAware'),
        ]);
    }
}

/**
 * @internal
 */
class NonStorageAwareField extends Field
{
    protected function getSerializerClass(): string
    {
        /** @phpstan-ignore return.type (for test purpose) */
        return '';
    }
}

/**
 * @internal
 */
class TestEntityWithAllPossibleFieldsDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_with_all_possible_fields';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new CreatedByField(),
            new UpdatedByField(),
            new StateMachineStateField('state_id', 'stateId', OrderStates::STATE_MACHINE),
            new CreatedAtField(),
            new UpdatedAtField(),
            new DateTimeField('datetime', 'datetime'),
            new DateField('date', 'date'),
            new CartPriceField('cart_price', 'cartPrice'),
            new CalculatedPriceField('calculated_price', 'calculatedPrice'),
            new PriceField('price', 'price'),
            new PriceDefinitionField('price_definition', 'priceDefinition'),
            new JsonField('json', 'json'),
            new ListField('list', 'list'),
            new ConfigJsonField('config_json', 'configJson'),
            new CustomFields(),
            new BreadcrumbField(),
            new CashRoundingConfigField('cash_rounding_config', 'cashRoundingConfig'),
            new ObjectField('object', 'object'),
            new TaxFreeConfigField('tax_free_config', 'taxFreeConfig'),
            new TreeBreadcrumbField('tree_breadcrumb', 'treeBreadcrumb'),
            new VariantListingConfigField('variant_listing_config', 'variantListingConfig'),
            new VersionDataPayloadField('version_data_payload', 'versionDataPayload'),
            new ChildCountField(),
            new IntField('int', 'int'),
            new AutoIncrementField(),
            new TreeLevelField('tree_level', 'treeLevel'),
            new BoolField('bool', 'bool'),
            new LockedField(),
            new PasswordField('password', 'password'),
            new StringField('string', 'string'),
            new TimeZoneField('timezone', 'timezone'),
            new CronIntervalField('cron_interval', 'cronInterval'),
            new DateIntervalField('date_interval', 'dateInterval'),
            new EmailField('email', 'email'),
            new RemoteAddressField('remote_address', 'remoteAddress'),
            new NumberRangeField('number_range', 'numberRange'),
            new BlobField('blob', 'blob'),
            new FloatField('float', 'float'),
            new TreePathField('tree_path', 'treePath'),
            new LongTextField('long_text', 'longText'),
        ]);
    }
}

/**
 * @internal
 */
class TestEntityWithForeignKeysDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity_with_foreign_keys';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new ParentFkField(self::class),
            (new ReferenceVersionField(self::class, 'parent_version_id'))->addFlags(new Required()),
            new FkField('association_id', 'associationId', TestAssociationDefinition::class),
            new ManyToOneAssociationField('association', 'association_id', TestAssociationDefinition::class, 'id'),
            new FkField('association_id2', 'associationId2', TestAssociationDefinition::class),
            (new ManyToOneAssociationField('association2', 'association_id2', TestAssociationDefinition::class, 'id'))->addFlags(new CascadeDelete()),
            new FkField('association_id3', 'associationId3', TestAssociationDefinition::class),
            (new ManyToOneAssociationField('association3', 'association_id3', TestAssociationDefinition::class, 'id'))->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('children', self::class, 'parent_id'),
            new ManyToManyAssociationField('manyToMany', self::class, TestAssociationDefinition::class, 'test_entity_with_foreign_keys_id', 'test_association_id'),
            new OneToOneAssociationField('oneToOne', 'id', 'test_entity_with_foreign_keys_id', self::class, true),
        ]);
    }
}

/**
 * @internal
 */
class TestAssociationDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_association';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name'),
        ]);
    }
}

/**
 * @internal
 */
class TestAssociationWithMissingReferenceVersionDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_association_with_missing_reference_version';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            new OneToOneAssociationField('product', 'product_id', 'id', ProductDefinition::class),
        ]);
    }
}

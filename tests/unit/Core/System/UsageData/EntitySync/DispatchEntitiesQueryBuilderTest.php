<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\EntitySync;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Query\Expression\CompositeExpression;
use Doctrine\DBAL\Query\Expression\ExpressionBuilder;
use Doctrine\DBAL\Result;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntitiesQueryBuilder;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Core\System\UsageData\EntitySync\Operation;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Doctrine\QueryBuilderDataExtractor;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(DispatchEntitiesQueryBuilder::class)]
class DispatchEntitiesQueryBuilderTest extends TestCase
{
    private DispatchEntitiesQueryBuilder $queryHelper;

    private MockObject&Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->connection->expects($this->never())
            ->method('createQueryBuilder');
        $this->connection->expects($this->any())
            ->method('createExpressionBuilder')
            ->willReturn(new ExpressionBuilder($this->connection));

        $this->queryHelper = new DispatchEntitiesQueryBuilder($this->connection);
    }

    public function testForEntityAddsTable(): void
    {
        static::assertSame($this->queryHelper, $this->queryHelper->forEntity('test_entity'));

        $from = QueryBuilderDataExtractor::getFrom($this->queryHelper->getQueryBuilder());
        static::assertCount(1, $from);
        static::assertSame(
            EntityDefinitionQueryHelper::escape('test_entity'),
            $from[0],
        );
    }

    public function testWithFieldsAddsStorageAwareFields(): void
    {
        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withFields(new FieldCollection([
                new StringField('storage_aware', 'storageAware'),
            ]))
        );

        $select = QueryBuilderDataExtractor::getSelect($this->queryHelper->getQueryBuilder());
        static::assertCount(1, $select);
        static::assertSame(
            EntityDefinitionQueryHelper::escape('storage_aware'),
            $select[0],
        );
    }

    public function testWithFieldsRemovesNonStorageAwareFields(): void
    {
        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withFields(new FieldCollection([
                new OneToOneAssociationField('OneToOne', 'one_to_one', 'reverse_one_to_one', 'reference_class'),
            ]))
        );

        static::assertEmpty(QueryBuilderDataExtractor::getSelect($this->queryHelper->getQueryBuilder()));
    }

    public function testWithPrimaryKeyAddsNothingForEmptyArray(): void
    {
        static::assertSame($this->queryHelper, $this->queryHelper->withPrimaryKeys([]));

        static::assertEmpty(QueryBuilderDataExtractor::getWhere($this->queryHelper->getQueryBuilder()));
    }

    public function testWithPrimaryKeysWithCombinedPrimaryKey(): void
    {
        $primaryKeys = [
            ['product_id' => '0189b18c26d87161aaa4a10465bfe164', 'category_id' => '018a27bbfb0771e2a1344024f48eb0fd'],
        ];

        static::assertSame($this->queryHelper, $this->queryHelper->withPrimaryKeys($primaryKeys));

        static::assertEquals(
            CompositeExpression::or(
                CompositeExpression::and(
                    '`product_id` = :pk_1',
                    '`category_id` = :pk_2',
                ),
            ),
            QueryBuilderDataExtractor::getWhere($this->queryHelper->getQueryBuilder()),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(2, $parameters);
        static::assertContains(Uuid::fromHexToBytes('0189b18c26d87161aaa4a10465bfe164'), $parameters);
        static::assertContains(Uuid::fromHexToBytes('018a27bbfb0771e2a1344024f48eb0fd'), $parameters);
    }

    public function testWithPrimaryKeysWithMultipleElements(): void
    {
        $primaryKeys = [
            ['id' => '0189b18c26d87161aaa4a10465bfe164'],
            ['id' => '018a27bbfb0771e2a1344024f6634aa5'],
        ];

        static::assertSame($this->queryHelper, $this->queryHelper->withPrimaryKeys($primaryKeys));

        static::assertEquals(
            CompositeExpression::or(
                CompositeExpression::and(
                    '`id` = :pk_1',
                ),
                CompositeExpression::and(
                    '`id` = :pk_2',
                ),
            ),
            QueryBuilderDataExtractor::getWhere($this->queryHelper->getQueryBuilder()),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(2, $parameters);
        static::assertContains(Uuid::fromHexToBytes('0189b18c26d87161aaa4a10465bfe164'), $parameters);
        static::assertContains(Uuid::fromHexToBytes('018a27bbfb0771e2a1344024f6634aa5'), $parameters);
    }

    public function testExecute(): void
    {
        // can't call execute with empty query
        $this->queryHelper->getQueryBuilder()->select('1');
        $this->connection->expects($this->once())
            ->method('executeQuery')
            ->willReturn(static::createStub(Result::class));

        $this->queryHelper->execute();
    }

    public function testFetchesOnlyLiveVersion(): void
    {
        $definition = new TestEntityDefinition();
        new StaticDefinitionInstanceRegistry(
            [$definition],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGateway::class),
        );

        static::assertSame($this->queryHelper, $this->queryHelper->checkLiveVersion($definition));

        static::assertEquals(
            CompositeExpression::and(
                \sprintf('%s = :versionId', EntityDefinitionQueryHelper::escape('version_id')),
                \sprintf('%s = :versionId', EntityDefinitionQueryHelper::escape('test_version_id')),
            ),
            QueryBuilderDataExtractor::getWhere($this->queryHelper->getQueryBuilder()),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(1, $parameters);
        static::assertContains(Uuid::fromHexToBytes(Defaults::LIVE_VERSION), $parameters);
    }

    public function testWithRunDateConstraintCreatedOperation(): void
    {
        $runDate = new \DateTimeImmutable();
        $message = new DispatchEntityMessage('product', Operation::CREATE, $runDate, []);

        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withLastApprovalDateConstraint($message, $runDate),
        );

        static::assertEquals(
            CompositeExpression::or(
                '`updated_at` IS NULL',
                '`updated_at` <= :lastApprovalDate',
            ),
            QueryBuilderDataExtractor::getWhere($this->queryHelper->getQueryBuilder()),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(1, $parameters);
        static::assertArrayHasKey('lastApprovalDate', $parameters);
        static::assertEquals($runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $parameters['lastApprovalDate']);
    }

    public function testWithRunDateConstraintUpdatedOperation(): void
    {
        $runDate = new \DateTimeImmutable();

        $message = new DispatchEntityMessage('product', Operation::UPDATE, $runDate, []);

        static::assertSame(
            $this->queryHelper,
            $this->queryHelper->withLastApprovalDateConstraint($message, $runDate),
        );

        static::assertEquals(
            CompositeExpression::and(
                '`updated_at` <= :lastApprovalDate',
            ),
            QueryBuilderDataExtractor::getWhere($this->queryHelper->getQueryBuilder()),
        );

        $parameters = $this->queryHelper->getQueryBuilder()->getParameters();
        static::assertCount(1, $parameters);
        static::assertArrayHasKey('lastApprovalDate', $parameters);
        static::assertEquals($runDate->format(Defaults::STORAGE_DATE_TIME_FORMAT), $parameters['lastApprovalDate']);
    }
}

/**
 * @internal
 */
class TestEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'category';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            new ReferenceVersionField(self::class, 'test_version_id'),
        ]);
    }
}

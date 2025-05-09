<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamMappingIndexingMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ProductStreamUpdater::class)]
class ProductStreamUpdaterTest extends TestCase
{
    public function testUpdaterCanBeDisabled(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->never())->method(static::anything());

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method(static::anything());

        /** @var StaticEntityRepository<ProductCollection> $repo */
        $repo = new StaticEntityRepository([]);

        $updater = new ProductStreamUpdater(
            $connectionMock,
            new ProductDefinition(),
            $repo,
            $messageBusMock,
            $this->createMock(ManyToManyIdFieldUpdater::class),
            false
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('product_stream', [
                    new EntityWriteResult('product-1', [], 'test', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createCLIContext()),
            ]),
            []
        );

        $updater->updateProducts(['1', '2'], Context::createDefaultContext());
        $updater->update($containerEvent);
    }

    /**
     * @param string[] $ids
     * @param array<int, array<string, bool|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testCriteriaWithUpdateProducts(array $ids, array $filters, Criteria $criteria): void
    {
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($filters);

        $criteria->addFilter(new EqualsAnyFilter('id', $ids));

        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
            function (Criteria $actualCriteria, Context $actualContext) use ($criteria, $context, $ids): array {
                static::assertEquals($criteria, $actualCriteria);
                static::assertEquals($context, $actualContext);

                return $ids;
            },
        ]);

        $updater = new ProductStreamUpdater(
            $connection,
            new ProductDefinition(),
            $repository,
            $this->createMock(MessageBusInterface::class),
            $this->createMock(ManyToManyIdFieldUpdater::class),
            true
        );

        $updater->updateProducts($ids, $context);
    }

    /**
     * @param string[] $ids
     * @param array<int, array<string, bool|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testCriteriaWithHandle(array $ids, array $filters, Criteria $criteria): void
    {
        $context = Context::createDefaultContext();
        $context->setConsiderInheritance(true);

        $message = new ProductStreamMappingIndexingMessage(Uuid::randomHex());

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchOne')
            ->willReturn(current(array_column($filters, 'api_filter')));

        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($ids);

        // 1 time to insert the new mapping, 1 time to update the product table with the new stream ids
        $connection
            ->expects($this->exactly(2))
            ->method('transactional')
            ->withAnyParameters();

        $definition = new ProductDefinition();
        /** @var StaticEntityRepository<ProductCollection> */
        $repository = new StaticEntityRepository([
            function (Criteria $actualCriteria, Context $actualContext) use ($criteria, $context, $ids): array {
                static::assertEquals($criteria, $actualCriteria);
                static::assertEquals($context, $actualContext);

                return $ids;
            },
            fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);
        $manyToManyFieldUpdater
            ->expects($this->once())
            ->method('update')
            ->with($definition->getEntityName(), $ids, Context::createDefaultContext(), 'streamIds');

        $updater = new ProductStreamUpdater(
            $connection,
            $definition,
            $repository,
            $this->createMock(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            true
        );

        $updater->handle($message);
    }

    /**
     * @return iterable<string, array<int, array<int, array<string, bool|string>|string>|Criteria>>
     */
    public static function filterProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'Active filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new EqualsFilter('product.active', true),
            ),
        ];

        yield 'Price filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'range',
                        'field' => 'product.cheapestPrice',
                        'parameters' => [
                            'lte' => 50,
                        ],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new RangeFilter('product.price', [RangeFilter::LTE => 50]),
                    new RangeFilter('product.prices.price', [RangeFilter::LTE => 50]),
                ]),
            ),
        ];

        yield 'Nested price filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'product.cheapestPrice',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new MultiFilter(MultiFilter::CONNECTION_OR, [
                        new RangeFilter('product.price', [RangeFilter::LTE => 50]),
                        new RangeFilter('product.prices.price', [RangeFilter::LTE => 50]),
                    ]),
                ]),
            ),
        ];

        yield 'Nested price percentage filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'cheapestPrice.percentage',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new MultiFilter(MultiFilter::CONNECTION_AND, [
                    new MultiFilter(MultiFilter::CONNECTION_OR, [
                        new RangeFilter('product.price.percentage', [RangeFilter::LTE => 50]),
                        new RangeFilter('product.prices.price.percentage', [RangeFilter::LTE => 50]),
                    ]),
                ]),
            ),
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer\Version;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceCollection;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceEntity;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\CountryAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\TaxAddToSalesChannelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\AbstractSalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Tax\TaxDefinition;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Rule\TrueRule;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Group('slow')]
class VersioningTest extends TestCase
{
    use CountryAddToSalesChannelTestBehaviour;
    use DataAbstractionLayerFieldTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToSalesChannelTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    private Connection $connection;

    private EntityRepository $customerRepository;

    private EntityRepository $orderRepository;

    private AbstractSalesChannelContextFactory $salesChannelContextFactory;

    private Processor $processor;

    private OrderPersisterInterface $orderPersister;

    private Context $context;

    protected function setUp(): void
    {
        $this->productRepository = static::getContainer()->get('product.repository');
        $this->categoryRepository = static::getContainer()->get('category.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->customerRepository = static::getContainer()->get('customer.repository');
        $this->orderRepository = static::getContainer()->get('order.repository');
        $this->salesChannelContextFactory = static::getContainer()->get(SalesChannelContextFactory::class);
        $this->processor = static::getContainer()->get(Processor::class);
        $this->orderPersister = static::getContainer()->get(OrderPersister::class);
        $this->context = Context::createDefaultContext();

        $this->registerDefinition(CalculatedPriceFieldTestDefinition::class);
    }

    public function testChangelogWrittenForRoot(): void
    {
        $id = Uuid::randomHex();
        $categoryId = Uuid::randomHex();

        $product = [
            'id' => $id,
            'productNumber' => $id,
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'updated', 'taxRate' => 11000],
            'categories' => [
                ['id' => $categoryId, 'name' => 'test'],
            ],
        ];

        $context = Context::createDefaultContext();

        $this->productRepository->create([$product], $context);

        $ruleId = Uuid::randomHex();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        $price = [
            'id' => $id,
            'productId' => $id,
            'quantityStart' => 1,
            'ruleId' => $ruleId,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 119,
                    'net' => 100,
                    'linked' => false,
                ],
            ],
        ];

        $priceRepository = static::getContainer()->get('product_price.repository');

        $event = $priceRepository->create([$price], $context);
        $productEvent = $event->getEventByEntityName('product');

        static::assertInstanceOf(EntityWrittenEvent::class, $productEvent);
        static::assertSame([$id], $productEvent->getIds());

        $versionId = $this->productRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);

        $priceRepository->delete([['id' => $id]], $version);

        $commits = $this->getCommits('product', $id, $versionId);
        static::assertCount(1, $commits);

        $mappingRepository = static::getContainer()->get('product_category.repository');

        $event = $mappingRepository->delete([['productId' => $id, 'categoryId' => $categoryId]], $version);

        $productEvent = $event->getEventByEntityName('product');
        static::assertInstanceOf(EntityWrittenEvent::class, $productEvent);

        $categoryEvent = $event->getEventByEntityName('category');
        static::assertInstanceOf(EntityWrittenEvent::class, $categoryEvent);

        // recursion test > order > delivery > position
    }

    public function testChangelogOnlyWrittenForVersionAwareEntities(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'taxRate' => 16,
            'name' => 'test',
        ];

        $context = Context::createDefaultContext();

        static::getContainer()->get('tax.repository')->create([$data], $context);

        $changelog = $this->getVersionData(static::getContainer()->get(TaxDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);

        $product = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'updated', 'taxRate' => 11000],
        ];

        $this->productRepository->upsert([$product], $context);

        $versionId = $this->productRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);
        $this->productRepository->update([['id' => $id, 'name' => 'test']], $version);
        $this->productRepository->merge($versionId, $context);

        $changelog = $this->getVersionData(static::getContainer()->get(TaxDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);

        $changelog = $this->getVersionData(static::getContainer()->get(ProductDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(1, $changelog);

        $changelog = $this->getVersionData(static::getContainer()->get(ProductManufacturerDefinition::class)->getEntityName(), $id, Defaults::LIVE_VERSION);
        static::assertCount(0, $changelog);
    }

    public function testDeleteNoneExistingVersion(): void
    {
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'p1'))
            ->price(100)
            ->build();

        $context = Context::createDefaultContext();

        static::getContainer()
            ->get('product.repository')
            ->create([$product], $context);

        $versionId = static::getContainer()
            ->get('product.repository')
            ->createVersion($ids->get('p1'), $context);

        $version = $context->createWithVersionId($versionId);

        static::getContainer()
            ->get('product.repository')
            ->delete([['id' => $ids->get('p1')]], $version);

        static::getContainer()
            ->get('version.repository')
            ->delete([['id' => $versionId]], $context);

        $e = null;

        try {
            static::getContainer()
                ->get('product.repository')
                ->merge($versionId, $context);
        } catch (DataAbstractionLayerException $e) {
        }

        static::assertInstanceOf(DataAbstractionLayerException::class, $e);
        static::assertSame(DataAbstractionLayerException::VERSION_NOT_EXISTS, $e->getErrorCode());

        $versions = static::getContainer()
            ->get(Connection::class)
            ->fetchFirstColumn(
                'SELECT LOWER(HEX(version_id)) FROM product WHERE id = :id',
                ['id' => Uuid::fromHexToBytes($ids->get('p1'))]
            );

        static::assertContains(Defaults::LIVE_VERSION, $versions);
    }

    public function testICanVersionPriceFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1000, 'net' => 1000, 'linked' => false]],
            ],
        ], $versionContext);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        $price = $product->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(100.0, $price->getGross());
        static::assertSame(10.0, $price->getNet());

        $product = $this->productRepository->search(new Criteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        $price = $product->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(1000.0, $price->getGross());
        static::assertSame(1000.0, $price->getNet());

        $this->productRepository->merge($versionId, $context);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        $price = $product->getCurrencyPrice(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price);
        static::assertSame(1000.0, $price->getGross());
        static::assertSame(1000.0, $price->getNet());
    }

    public function testICanVersionDateTimeFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
            'releaseDate' => '2018-01-01',
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'releaseDate' => '2018-10-05',
            ],
        ], $versionContext);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(\DateTimeInterface::class, $product->getReleaseDate());
        static::assertSame('2018-01-01', $product->getReleaseDate()->format('Y-m-d'));

        $product = $this->productRepository->search(new Criteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(\DateTimeInterface::class, $product->getReleaseDate());
        static::assertSame('2018-10-05', $product->getReleaseDate()->format('Y-m-d'));

        $this->productRepository->merge($versionId, $context);

        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(\DateTimeInterface::class, $product->getReleaseDate());
        static::assertSame('2018-10-05', $product->getReleaseDate()->format('Y-m-d'));
    }

    public function testICanVersionCalculatedPriceField(): void
    {
        $id = Uuid::randomHex();

        $this->connection->rollBack();

        $this->connection->executeStatement(CalculatedPriceFieldTestDefinition::getCreateTable());

        $this->connection->beginTransaction();

        $price = new CalculatedPrice(
            100.20,
            100.30,
            new CalculatedTaxCollection([
                new CalculatedTax(0.19, 10, 10),
                new CalculatedTax(0.19, 5, 10),
            ]),
            new TaxRuleCollection([
                new TaxRule(10.0, 50),
                new TaxRule(5, 50),
            ])
        );

        $definition = static::getContainer()->get(CalculatedPriceFieldTestDefinition::class);
        static::assertInstanceOf(CalculatedPriceFieldTestDefinition::class, $definition);
        $repository = new EntityRepository(
            $definition,
            static::getContainer()->get(EntityReaderInterface::class),
            static::getContainer()->get(VersionManager::class),
            static::getContainer()->get(EntitySearcherInterface::class),
            static::getContainer()->get(EntityAggregatorInterface::class),
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(EntityLoadedEventFactory::class)
        );

        $context = Context::createDefaultContext();

        $data = ['id' => $id, 'price' => $price];

        $repository->create([$data], $context);

        $versionId = $repository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $newPrice = new CalculatedPrice(
            500.20,
            500.30,
            new CalculatedTaxCollection([
                new CalculatedTax(3.50, 15, 500.20),
                new CalculatedTax(3.50, 30, 500.20),
            ]),
            new TaxRuleCollection([
                new TaxRule(15, 30),
                new TaxRule(30, 70),
            ])
        );

        $updated = ['id' => $id, 'price' => $newPrice];
        $repository->update([$updated], $versionContext);

        $entity = $repository
            ->search(new Criteria([$id]), $context)
            ->first();

        // check that the live entity contains the original price
        static::assertInstanceOf(ArrayEntity::class, $entity);

        $livePrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $livePrice);

        static::assertSame(100.20, $livePrice->getUnitPrice());
        static::assertSame(100.30, $livePrice->getTotalPrice());
        static::assertSame(0.38, $livePrice->getCalculatedTaxes()->getAmount());

        // check that the version entity is updated with the new price
        $entity = $repository
            ->search(new Criteria([$id]), $versionContext)
            ->first();

        static::assertInstanceOf(ArrayEntity::class, $entity);

        $versionPrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $versionPrice);

        static::assertSame(500.20, $versionPrice->getUnitPrice());
        static::assertSame(500.30, $versionPrice->getTotalPrice());
        static::assertSame(7.00, $versionPrice->getCalculatedTaxes()->getAmount());

        $repository->merge($versionId, $context);

        // check that the version entity is updated with the new price
        $entity = $repository
            ->search(new Criteria([$id]), $context)
            ->first();

        static::assertInstanceOf(ArrayEntity::class, $entity);

        $versionPrice = $entity->get('price');
        static::assertInstanceOf(CalculatedPrice::class, $versionPrice);

        static::assertSame(500.20, $versionPrice->getUnitPrice());
        static::assertSame(500.30, $versionPrice->getTotalPrice());
        static::assertSame(7.00, $versionPrice->getCalculatedTaxes()->getAmount());

        $this->connection->rollBack();

        $this->connection->executeStatement(CalculatedPriceFieldTestDefinition::dropTable());
        // We have created a table so the transaction rollback don't work -> we have to do it manually
        $this->connection->executeStatement('DELETE FROM version_commit WHERE version_id = ?', [Uuid::fromHexToBytes($versionId)]);

        $this->connection->beginTransaction();
    }

    public function testICanVersionCalculatedFields(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $id3 = Uuid::randomHex();

        $data = [
            'id' => $id1,
            'name' => 'category-1',
            'children' => [
                [
                    'id' => $id2,
                    'name' => 'category-2',
                    'children' => [
                        [
                            'id' => $id3,
                            'name' => 'category-3',
                        ],
                    ],
                ],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->categoryRepository->create([$data], $context);

        $versionId = $this->categoryRepository->createVersion($id3, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $category = $this->categoryRepository->search(new Criteria([$id3]), $versionContext)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|' . $id2 . '|', $category->getPath());

        // update parent of last category in version scope
        $updated = ['id' => $id3, 'parentId' => $id1];

        $this->categoryRepository->update([$updated], $versionContext);

        // check that the path updated
        $category = $this->categoryRepository->search(new Criteria([$id3]), $versionContext)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|', $category->getPath());

        $category = $this->categoryRepository->search(new Criteria([$id3]), $context)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|' . $id2 . '|', $category->getPath());

        $this->categoryRepository->merge($versionId, $context);

        // test after merge the path is updated too
        $category = $this->categoryRepository->search(new Criteria([$id3]), $context)->getEntities()->first();
        static::assertNotNull($category);
        static::assertSame('|' . $id1 . '|', $category->getPath());
    }

    public function testICanVersionTranslatedFields(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);

        $this->productRepository->update([['id' => $id, 'name' => 'test']], $version);
        $this->productRepository->merge($versionId, $context);

        $changelog = $this->getTranslationVersionData(static::getContainer()->get(ProductTranslationDefinition::class)->getEntityName(), Defaults::LANGUAGE_SYSTEM, 'productId', $id, $context->getVersionId(), 'productVersionId');

        static::assertCount(1, $changelog);
        static::assertArrayHasKey('name', $changelog[0]['payload']);
        static::assertSame('test', $changelog[0]['payload']['name']);

        $versionId = $this->productRepository->createVersion($id, $context);
        $version = $context->createWithVersionId($versionId);
        $this->productRepository->update([['id' => $id, 'name' => 'updated']], $version);
        $this->productRepository->merge($versionId, $context);

        $changelog = $this->getTranslationVersionData(static::getContainer()->get(ProductTranslationDefinition::class)->getEntityName(), Defaults::LANGUAGE_SYSTEM, 'productId', $id, $context->getVersionId(), 'productVersionId');

        static::assertCount(2, $changelog);
        static::assertArrayHasKey('name', $changelog[1]['payload']);
        static::assertSame('updated', $changelog[1]['payload']['name']);
    }

    public function testChangelogWrittenForUpdate(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $version = $context->createWithVersionId($versionId);

        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $version);

        $this->productRepository->merge($versionId, $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());

        static::assertCount(1, $changelog);

        // check update written
        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertSame('product', $changelog[0]['entity_name']);
        static::assertSame('update', $changelog[0]['action']);
    }

    public function testChangelogAppliedAfterMerge(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $changelog = $this->getVersionData('product', $id, $versionId);

        static::assertCount(1, $changelog);

        // check insert written
        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('product', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $changelog = $this->getVersionData('product', $id, $versionId);

        static::assertCount(2, $changelog);

        // check insert written
        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('product', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        static::assertSame($id, $changelog[1]['entity_id']['id']);
        static::assertSame($versionId, $changelog[1]['entity_id']['versionId']);
        static::assertSame('product', $changelog[1]['entity_name']);
        static::assertSame('update', $changelog[1]['action']);

        static::assertArrayHasKey('payload', $changelog[1]);
        static::assertArrayHasKey('ean', $changelog[1]['payload']);
        static::assertSame('updated', $changelog[1]['payload']['ean']);

        $this->productRepository->merge($versionId, $context);

        $changelog = $this->getVersionData('product', $id, $context->getVersionId());
        static::assertCount(1, $changelog);

        static::assertSame($id, $changelog[0]['entity_id']['id']);
        static::assertSame($context->getVersionId(), $changelog[0]['entity_id']['versionId']);
        static::assertSame('product', $changelog[0]['entity_name']);
        static::assertSame('update', $changelog[0]['action']);

        static::assertArrayHasKey('payload', $changelog[0]);
        static::assertArrayHasKey('ean', $changelog[0]['payload']);
        static::assertSame('updated', $changelog[0]['payload']['ean']);
    }

    public function testICanVersionOneToManyAssociations(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'name' => 'to clone',
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        // check both products exists
        $products = $this->connection->fetchAllAssociative('SELECT * FROM product WHERE id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(2, $products);

        $versions = array_map(fn ($item) => Uuid::fromBytesToHex($item['version_id']), $products);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $prices = $this->connection->fetchAllAssociative('SELECT * FROM product_price WHERE product_id = :id', ['id' => Uuid::fromHexToBytes($productId)]);
        static::assertCount(4, $prices);

        $versionPrices = array_filter($prices, function (array $price) use ($versionId) {
            $version = Uuid::fromBytesToHex($price['version_id']);

            return $version === $versionId;
        });

        static::assertCount(2, $versionPrices);
        foreach ($versionPrices as $price) {
            $productVersionId = Uuid::fromBytesToHex($price['product_version_id']);
            static::assertSame($versionId, $productVersionId);
        }
    }

    public function testICanVersionManyToManyAssociations(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        // check both products exists
        $products = $this->connection->fetchAllAssociative(
            'SELECT * FROM product WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($productId)]
        );
        static::assertCount(2, $products);

        $versions = array_map(fn ($item) => Uuid::fromBytesToHex($item['version_id']), $products);

        static::assertContains(Defaults::LIVE_VERSION, $versions);
        static::assertContains($versionId, $versions);

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id = :id AND product_version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]
        );
        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id = :id AND product_version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes($versionId)]
        );
        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }
    }

    public function testICanReadASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $product = $this->productRepository->search(new Criteria([$id]), $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('updated', $product->getEan());

        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('EAN', $product->getEan());

        $this->productRepository->merge($versionId, $context);
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('updated', $product->getEan());
    }

    public function testICanReadOneToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        // create live product with two prices
        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false],
            ],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false],
                    ],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [
                        ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false],
                    ],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        // create new version of the product, product and prices rows are duplicated now
        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        // update prices in version scope
        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                ],
            ],
        ];

        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository->search($criteria, $versionContext)->first();

        // check if the prices are updated in the version scope
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());
        static::assertInstanceOf(ProductPriceEntity::class, $product->getPrices()->get($priceId1));
        $price1 = $product->getPrices()->get($priceId1)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price1);
        static::assertSame(100.0, $price1->getGross());
        static::assertSame(100.0, $price1->getNet());
        static::assertInstanceOf(ProductPriceEntity::class, $product->getPrices()->get($priceId2));
        $price2 = $product->getPrices()->get($priceId2)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price2);
        static::assertSame(99.0, $price2->getGross());
        static::assertSame(99.0, $price2->getNet());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository->search($criteria, $context)->first();

        // check the prices of the live version are untouched
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());
        static::assertInstanceOf(ProductPriceEntity::class, $product->getPrices()->get($priceId1));
        $price1 = $product->getPrices()->get($priceId1)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price1);
        static::assertSame(15.0, $price1->getGross());
        static::assertSame(15.0, $price1->getNet());
        static::assertInstanceOf(ProductPriceEntity::class, $product->getPrices()->get($priceId2));
        $price2 = $product->getPrices()->get($priceId2)->getPrice()->get(Defaults::CURRENCY);
        static::assertInstanceOf(Price::class, $price2);
        static::assertSame(10.0, $price2->getGross());
        static::assertSame(10.0, $price2->getNet());

        // now delete the prices in version context
        $priceRepository = static::getContainer()->get('product_price.repository');
        $priceRepository->delete([
            ['id' => $priceId1, 'versionId' => $versionId],
            ['id' => $priceId2, 'versionId' => $versionId],
        ], $versionContext);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository->search($criteria, $context)->first();

        // live version scope should be untouched
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());

        // version scope should have no prices
        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository->search($criteria, $versionContext)->first();
        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(0, $product->getPrices());

        // now add new prices
        $newPriceId1 = Uuid::randomHex();
        $newPriceId2 = Uuid::randomHex();
        $newPriceId3 = Uuid::randomHex();

        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $newPriceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                ],
                [
                    'id' => $newPriceId2,
                    'quantityStart' => 21,
                    'quantityEnd' => 100,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 8, 'linked' => false]],
                ],
                [
                    'id' => $newPriceId3,
                    'quantityStart' => 101,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 3, 'linked' => false]],
                ],
            ],
        ];

        // add new price matrix to product
        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository->search($criteria, $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(2, $product->getPrices());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository->search($criteria, $versionContext)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(3, $product->getPrices());

        $this->productRepository->merge($versionId, $context);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository->search($criteria, $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(3, $product->getPrices());

        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $newPriceId4 = Uuid::randomHex();

        // check that we can add entities into a sub version using the sub entity repository
        $data = [
            'id' => $newPriceId4,
            'productId' => $productId,
            'currencyId' => Defaults::CURRENCY,
            'quantityStart' => 101,
            'ruleId' => $ruleId,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 5, 'net' => 3, 'linked' => false]],
        ];

        $priceRepository->create([$data], $versionContext);

        $price4 = $priceRepository->search(new Criteria([$newPriceId4]), $versionContext)->first();
        static::assertInstanceOf(ProductPriceEntity::class, $price4);

        static::assertInstanceOf(Price::class, $price4->getPrice()->get(Defaults::CURRENCY));
        static::assertSame(5.0, $price4->getPrice()->get(Defaults::CURRENCY)->getGross());
        static::assertSame($newPriceId4, $price4->getId());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_price.productId', $productId));

        $prices = $priceRepository->search($criteria, $versionContext);
        static::assertCount(4, $prices);
        static::assertContains($newPriceId4, $prices->getIds());
    }

    public function testICanReadManyToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('categories');

        $product = $this->productRepository
            ->search($criteria, $context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(CategoryCollection::class, $product->getCategories());
        static::assertCount(2, $product->getCategories());

        $categories = $this->connection->fetchAllAssociative(
            'SELECT * FROM product_category WHERE product_id = :id AND product_version_id = :version',
            ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes($versionId)]
        );

        static::assertCount(2, $categories);

        foreach ($categories as $category) {
            $categoryVersion = Uuid::fromBytesToHex($category['category_version_id']);
            static::assertSame(Defaults::LIVE_VERSION, $categoryVersion);
        }

        $product = $this->productRepository
            ->search($criteria, $versionContext)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(CategoryCollection::class, $product->getCategories());
        static::assertCount(2, $product->getCategories());

        $this->productRepository->merge($versionId, $context);

        $product = $this->productRepository
            ->search($criteria, $context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(CategoryCollection::class, $product->getCategories());
        static::assertCount(2, $product->getCategories());
    }

    public function testICanSearchInASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1000, 'net' => 1000, 'linked' => false]],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price.gross', 1000));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(0, $products);

        $products = $this->productRepository->search($criteria, $versionContext);
        static::assertCount(1, $products);

        $this->productRepository->merge($versionId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.price.gross', 1000));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(1, $products);
    }

    public function testICanSearchOneToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        // create live product with two prices
        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        // create new version of the product, product and prices rows are duplicated now
        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        // update prices in version scope
        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                ],
            ],
        ];

        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.prices.price.gross', 100));

        // live search shouldn't find anything because the 1000.00 price is only defined in version scope
        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(0, $result->getIds());

        // version contains should have the price
        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());

        // delete second price to check if the delete is applied too
        static::getContainer()->get('product_price.repository')->delete([
            ['id' => $priceId2, 'versionId' => $versionId],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.prices.price.gross', 99));
        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(0, $result->getIds());

        $this->productRepository->merge($versionId, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.prices.price.gross', 100));

        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());

        $criteria = new Criteria([$productId]);
        $criteria->addAssociation('prices');

        $product = $this->productRepository
            ->search($criteria, $context)
            ->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(ProductPriceCollection::class, $product->getPrices());
        static::assertCount(1, $product->getPrices());
    }

    public function testICanSearchManyToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $categoryId3 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $updated = [
            'id' => $productId,
            'categories' => [
                ['id' => $categoryId3, 'name' => 'matching value'],
            ],
        ];
        $this->productRepository->update([$updated], $versionContext);

        // create criteria which should match only the version scope
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.categories.name', 'matching value'));

        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(0, $result->getIds());

        $result = $this->productRepository->searchIds($criteria, $versionContext);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());

        $Criteria = new Criteria([$productId]);
        $Criteria->addAssociation('categories');

        $product = $this->productRepository->search($Criteria, $versionContext)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertInstanceOf(CategoryCollection::class, $product->getCategories());
        static::assertCount(3, $product->getCategories());

        $this->productRepository->merge($versionId, $context);

        $result = $this->productRepository->searchIds($criteria, $context);
        static::assertCount(1, $result->getIds());
        static::assertContains($productId, $result->getIds());
    }

    public function testSearchConsidersLiveVersionAsFallback(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => null,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = $this->productRepository->createVersion($id2, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([['id' => $id2, 'ean' => 'EAN']], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(1, $products);

        // in this version we have two products with the ean value "EAN"
        $products = $this->productRepository->search($criteria, $versionContext);
        static::assertCount(2, $products);

        $this->productRepository->merge($versionId, $context);

        $products = $this->productRepository->search($criteria, $context);
        static::assertCount(2, $products);
    }

    public function testICanAggregateInASpecifyVersion(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1000, 'net' => 1000, 'linked' => false]],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('sum_price', 'product.price.gross'));

        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(100.0, $sum->getSum());

        $aggregations = $this->productRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());

        $this->productRepository->merge($versionId, $context);

        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());
    }

    public function testICanAggregateOneToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $ruleId = Uuid::randomHex();
        $priceId1 = Uuid::randomHex();
        $priceId2 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        static::getContainer()->get('rule.repository')->create([
            ['id' => $ruleId, 'name' => 'test', 'priority' => 1],
        ], $context);

        // create live product with two prices
        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'name' => 'to clone',
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'prices' => [
                [
                    'id' => $priceId1,
                    'quantityStart' => 1,
                    'quantityEnd' => 20,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 15, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'quantityStart' => 21,
                    'ruleId' => $ruleId,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 10, 'linked' => false]],
                ],
            ],
        ];

        $this->productRepository->create([$product], $context);

        // create new version of the product, product and prices rows are duplicated now
        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        // update prices in version scope
        $updated = [
            'id' => $productId,
            'prices' => [
                [
                    'id' => $priceId1,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false]],
                ],
                [
                    'id' => $priceId2,
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 99, 'net' => 99, 'linked' => false]],
                ],
            ],
        ];

        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addAggregation(new SumAggregation('sum_prices', 'product.prices.price.gross'));

        $result = $this->productRepository->aggregate($criteria, $context);
        $aggregation = $result->get('sum_prices');

        static::assertInstanceOf(SumResult::class, $aggregation);
        static::assertSame(25.0, $aggregation->getSum());

        $result = $this->productRepository->aggregate($criteria, $versionContext);
        $aggregation = $result->get('sum_prices');

        static::assertInstanceOf(SumResult::class, $aggregation);
        static::assertSame(199.0, $aggregation->getSum());

        $this->productRepository->merge($versionId, $context);

        $result = $this->productRepository->aggregate($criteria, $context);
        $aggregation = $result->get('sum_prices');

        static::assertInstanceOf(SumResult::class, $aggregation);
        static::assertSame(199.0, $aggregation->getSum());
    }

    public function testICanAggregateManyToManyInASpecifyVersion(): void
    {
        $productId = Uuid::randomHex();
        $categoryId1 = Uuid::randomHex();
        $categoryId2 = Uuid::randomHex();
        $categoryId3 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $product = [
            'id' => $productId,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'to clone',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 1, 'net' => 1, 'linked' => false]],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $categoryId1, 'name' => 'cat1'],
                ['id' => $categoryId2, 'name' => 'cat2'],
            ],
        ];

        $this->productRepository->create([$product], $context);

        $versionId = $this->productRepository->createVersion($productId, $context);
        $versionContext = $context->createWithVersionId($versionId);

        $updated = [
            'id' => $productId,
            'categories' => [
                ['id' => $categoryId3, 'name' => 'matching value'],
            ],
        ];
        $this->productRepository->update([$updated], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.id', $productId));
        $criteria->addAggregation(new CountAggregation('category_count', 'product.categories.id'));

        $result = $this->productRepository->aggregate($criteria, $context);
        $aggregation = $result->get('category_count');

        static::assertInstanceOf(CountResult::class, $aggregation);
        static::assertSame(2, $aggregation->getCount());

        $result = $this->productRepository->aggregate($criteria, $versionContext);
        $aggregation = $result->get('category_count');

        static::assertInstanceOf(CountResult::class, $aggregation);
        static::assertSame(3, $aggregation->getCount());

        $this->productRepository->merge($versionId, $context);

        $result = $this->productRepository->aggregate($criteria, $context);
        $aggregation = $result->get('category_count');

        static::assertInstanceOf(CountResult::class, $aggregation);
        static::assertSame(3, $aggregation->getCount());
    }

    public function testAggregateConsidersLiveVersionAsFallback(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();
        $data = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = $this->productRepository->createVersion($id1, $context);

        $versionContext = $context->createWithVersionId($versionId);

        $this->productRepository->update([
            [
                'id' => $id1,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 900, 'net' => 900, 'linked' => false]],
            ],
        ], $versionContext);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.ean', 'EAN'));
        $criteria->addAggregation(new SumAggregation('sum_price', 'product.price.gross'));

        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(200.0, $sum->getSum());

        $aggregations = $this->productRepository->aggregate($criteria, $versionContext);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());

        $this->productRepository->merge($versionId, $context);
        $aggregations = $this->productRepository->aggregate($criteria, $context);
        static::assertTrue($aggregations->has('sum_price'));
        $sum = $aggregations->get('sum_price');
        static::assertInstanceOf(SumResult::class, $sum);
        static::assertSame(1000.0, $sum->getSum());
    }

    public function testICanAddEntitiesToSpecifyVersion(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $data = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN-1',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'test',
                'ean' => 'EAN-2',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'create'],
                'tax' => ['name' => 'create', 'taxRate' => 1],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($data, $context);

        $versionId = Uuid::randomHex();
        $this->productRepository->createVersion($id1, $context, 'campaign', $versionId);
        $this->productRepository->createVersion($id2, $context, 'campaign', $versionId);

        // check changelog written for product 1
        $changelog = $this->getVersionData('product', $id1, $versionId);
        static::assertCount(1, $changelog);
        static::assertSame($id1, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('product', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        // check changelog written for product 2 with same version
        $changelog = $this->getVersionData('product', $id2, $versionId);
        static::assertCount(1, $changelog);
        static::assertSame($id2, $changelog[0]['entity_id']['id']);
        static::assertSame($versionId, $changelog[0]['entity_id']['versionId']);
        static::assertSame('product', $changelog[0]['entity_name']);
        static::assertSame('clone', $changelog[0]['action']);

        // update products of specify version
        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->update(
            [
                ['id' => $id1, 'ean' => 'EAN-1-update'],
                ['id' => $id2, 'ean' => 'EAN-2-update'],
            ],
            $versionContext
        );

        $products = $this->productRepository->search(new Criteria([$id1, $id2]), $versionContext)->getEntities();
        // check both products updated
        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertSame('EAN-1-update', $products->get($id1)->getEan());
        static::assertSame('EAN-2-update', $products->get($id2)->getEan());

        // check existing live version not to be updated
        $products = $this->productRepository->search(new Criteria([$id1, $id2]), $context)->getEntities();
        static::assertInstanceOf(ProductCollection::class, $products);
        static::assertCount(2, $products);
        static::assertTrue($products->has($id1));
        static::assertTrue($products->has($id2));
        static::assertSame('EAN-1', $products->get($id1)->getEan());
        static::assertSame('EAN-2', $products->get($id2)->getEan());

        // do merge
        $this->productRepository->merge($versionId, $context);

        // check both products are merged
        $products = $this->productRepository->search(new Criteria([$id1, $id2]), $context)->getEntities();
        static::assertCount(2, $products);
        $firstProduct = $products->get($id1);
        static::assertNotNull($firstProduct);
        $secondProduct = $products->get($id2);
        static::assertNotNull($secondProduct);
        static::assertSame('EAN-1-update', $firstProduct->getEan());
        static::assertSame('EAN-2-update', $secondProduct->getEan());
    }

    public function testVersionCommitUtf8(): void
    {
        $product = [
            'id' => Uuid::randomHex(),
            'productNumber' => Uuid::randomHex(),
            'name' => '😄',
            'stock' => 1,
            'ean' => 'EAN-1',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $affected = static::getContainer()->get('product.repository')->create([$product], Context::createDefaultContext());
        static::assertInstanceOf(EntityWrittenEvent::class, $affected->getEventByEntityName(ProductTranslationDefinition::ENTITY_NAME));
        $writtenProductTranslations = $affected->getEventByEntityName(ProductTranslationDefinition::ENTITY_NAME)->getPayloads();

        static::assertCount(1, $writtenProductTranslations);
        static::assertSame('😄', $writtenProductTranslations[0]['name']);
    }

    public function testCreateOrderVersion(): void
    {
        $ruleId = Uuid::randomHex();
        $customerId = $this->createCustomer();
        $paymentMethodId = $this->createPaymentMethod($ruleId);
        $this->addCountriesToSalesChannel();

        $context = $this->salesChannelContextFactory->create(
            Uuid::randomHex(),
            TestDefaults::SALES_CHANNEL,
            [
                SalesChannelContextService::CUSTOMER_ID => $customerId,
                SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId,
            ]
        );
        $context->setRuleIds(
            [$ruleId, $context->getShippingMethod()->getAvailabilityRuleId() ?? Uuid::randomHex()]
        );

        $cart = $this->createDemoCart($context);

        $cart = $this->processor->process($cart, $context, new CartBehavior());

        $id = $this->orderPersister->persist($cart, $context);

        $versionId = $this->orderRepository->createVersion($id, $this->context);

        static::assertTrue(Uuid::isValid($versionId));
    }

    public function testCascadeDeleteFlag(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'tax' => ['name' => 'create', 'taxRate' => 1],
            'productReviews' => [
                [
                    'id' => $id,
                    'customerId' => $this->createCustomer(),
                    'salesChannelId' => TestDefaults::SALES_CHANNEL,
                    'title' => 'Title',
                    'content' => 'Content',
                ],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        $version = Uuid::randomHex();

        static::assertSame(1, $this->getReviewCount($id, Defaults::LIVE_VERSION));

        $this->productRepository->createVersion($id, Context::createDefaultContext(), 'test', $version);

        static::assertSame(1, $this->getReviewCount($id, Defaults::LIVE_VERSION));

        static::assertSame(0, $this->getReviewCount($id, $version));
    }

    public function testMergingIsLocked(): void
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'productNumber' => Uuid::randomHex(),
            'stock' => 1,
            'name' => 'test',
            'ean' => 'EAN',
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 10, 'linked' => false]],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => 1],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create([$data], $context);

        $versionId = $this->productRepository->createVersion($id, $context);
        $versionContext = $context->createWithVersionId($versionId);
        $this->productRepository->upsert([['id' => $id, 'ean' => 'updated']], $versionContext);

        $commits = $this->getCommits('product', $id, $versionId);
        static::assertCount(2, $commits);

        $lockFactory = static::getContainer()->get('lock.factory');
        $lock = $lockFactory->createLock('sw-merge-version-' . $versionId);
        $lock->acquire();

        $exceptionWasThrown = false;

        try {
            $this->productRepository->merge($versionId, $context);
        } catch (DataAbstractionLayerException) {
            $exceptionWasThrown = true;
        } finally {
            $lock->release();
        }

        static::assertTrue($exceptionWasThrown);

        // assert that commits are not removed
        $commits = $this->getCommits('product', $id, $versionId);
        static::assertCount(2, $commits);

        // assert that changes are not applied
        $product = $this->productRepository->search(new Criteria([$id]), $context)->first();

        static::assertInstanceOf(ProductEntity::class, $product);
        static::assertSame('EAN', $product->getEan());
    }

    public function testMergeInCorrectOrder(): void
    {
        // we want to ensure that the data of a commit is persisted in the correct order
        $ids = new IdsCollection();

        $product = (new ProductBuilder($ids, 'p1'))
            ->price(100);

        $live = Context::createDefaultContext();

        static::getContainer()->get('product.repository')
            ->create([$product->build()], $live);

        // after having a simple product - create new version
        $versionId = $this->productRepository->createVersion($ids->get('p1'), $live);

        $version = $live->createWithVersionId($versionId);

        // now we want to create a manufacturer and update the product record at the same time
        $update = (new ProductBuilder($ids, 'p1'))
            ->manufacturer('manufacturer');

        $this->productRepository->update([$update->build()], $version);

        // when the version is merged - the manufacturer should be created first
        static::getContainer()->get('product.repository')->merge($versionId, $live);
    }

    private function getReviewCount(string $productId, string $versionId): int
    {
        return (int) $this->connection
            ->fetchOne(
                'SELECT COUNT(*) FROM product_review WHERE product_id = :id AND product_version_id = :version',
                ['id' => Uuid::fromHexToBytes($productId), 'version' => Uuid::fromHexToBytes($versionId)]
            );
    }

    private function createDemoCart(SalesChannelContext $salesChannelContext): Cart
    {
        $cart = new Cart('a-b-c');

        $id = Uuid::randomHex();

        $product = [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 119.99, 'net' => 99.99, 'linked' => false],
            ],
            'productNumber' => Uuid::randomHex(),
            'manufacturer' => ['name' => 'test'],
            'tax' => ['id' => Uuid::randomHex(), 'taxRate' => 19, 'name' => 'test'],
            'stock' => 10,
            'active' => true,
            'visibilities' => [
                ['salesChannelId' => TestDefaults::SALES_CHANNEL, 'visibility' => ProductVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        static::getContainer()->get('product.repository')
            ->create([$product], Context::createDefaultContext());

        $this->addTaxDataToSalesChannel($salesChannelContext, $product['tax']);

        $cart->add(
            (new LineItem($id, LineItem::PRODUCT_LINE_ITEM_TYPE, $id, 1))
                ->setStackable(true)
                ->setRemovable(true)
        );

        return $cart;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::randomHex() . '@example.com',
            'password' => TestDefaults::HASHED_PASSWORD,
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->customerRepository->upsert([$customer], Context::createDefaultContext());

        return $customerId;
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getCommits(string $entity, string $id, string $versionId): array
    {
        $data = $this->connection->fetchAllAssociative(
            'SELECT d.*
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, \'$.id\') = :id
             ORDER BY auto_increment',
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );

        return array_map(function (array $row) {
            $row['entity_id'] = json_decode((string) $row['entity_id'], true, 512, \JSON_THROW_ON_ERROR);
            $row['payload'] = json_decode((string) $row['payload'], true, 512, \JSON_THROW_ON_ERROR);

            return $row;
        }, $data);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getVersionData(string $entity, string $id, string $versionId): array
    {
        $data = $this->connection->fetchAllAssociative(
            'SELECT d.*
             FROM version_commit_data d
             INNER JOIN version_commit c
               ON c.id = d.version_commit_id
               AND c.version_id = :version
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, \'$.id\') = :id
             ORDER BY auto_increment',
            [
                'entity' => $entity,
                'id' => $id,
                'version' => Uuid::fromHexToBytes($versionId),
            ]
        );

        return array_map(function (array $row) {
            $row['entity_id'] = json_decode((string) $row['entity_id'], true, 512, \JSON_THROW_ON_ERROR);
            $row['payload'] = json_decode((string) $row['payload'], true, 512, \JSON_THROW_ON_ERROR);

            return $row;
        }, $data);
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function getTranslationVersionData(string $entity, string $languageId, string $foreignKeyName, string $foreignKey, string $versionId, string $versionField = 'versionId'): array
    {
        $data = $this->connection->fetchAllAssociative(
            'SELECT *
             FROM version_commit_data
             WHERE entity_name = :entity
             AND JSON_EXTRACT(entity_id, \'$.' . $foreignKeyName . '\') = :id
             AND JSON_EXTRACT(entity_id, \'$.languageId\') = :language
             AND JSON_EXTRACT(entity_id, \'$.' . $versionField . '\') = :version
             ORDER BY auto_increment',
            [
                'entity' => $entity,
                'id' => $foreignKey,
                'language' => $languageId,
                'version' => $versionId,
            ]
        );

        return array_map(function (array $row) {
            $row['entity_id'] = json_decode((string) $row['entity_id'], true, 512, \JSON_THROW_ON_ERROR);
            $row['payload'] = json_decode((string) $row['payload'], true, 512, \JSON_THROW_ON_ERROR);

            return $row;
        }, $data);
    }

    private function createPaymentMethod(string $ruleId): string
    {
        $paymentMethodId = Uuid::randomHex();
        $repository = static::getContainer()->get('payment_method.repository');

        $ruleRegistry = static::getContainer()->get(RuleConditionRegistry::class);
        $prop = ReflectionHelper::getProperty(RuleConditionRegistry::class, 'rules');
        $prop->setValue($ruleRegistry, array_merge($prop->getValue($ruleRegistry), ['true' => new TrueRule()]));

        $data = [
            'id' => $paymentMethodId,
            'handlerIdentifier' => TestPaymentHandler::class,
            'name' => 'Payment',
            'technicalName' => 'payment_test',
            'active' => true,
            'position' => 0,
            'availabilityRule' => [
                'id' => $ruleId,
                'name' => 'true',
                'priority' => 0,
                'conditions' => [
                    [
                        'type' => 'true',
                    ],
                ],
            ],
            'salesChannels' => [
                [
                    'id' => TestDefaults::SALES_CHANNEL,
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $paymentMethodId;
    }
}

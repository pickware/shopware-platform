<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\ProductCrossSellingSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts\ProductCrossSellingAssignedProductsEntity;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ProductCrossSellingSerializerTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testOnlySupportsProductCrossSelling(): void
    {
        /** @var EntityRepository $assignedProductsRepository */
        $assignedProductsRepository = static::getContainer()->get('product_cross_selling_assigned_products.repository');

        $serializer = new ProductCrossSellingSerializer($assignedProductsRepository);

        static::assertTrue($serializer->supports(ProductCrossSellingDefinition::ENTITY_NAME), 'should support product cross selling');

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);
        foreach ($definitionRegistry->getDefinitions() as $definition) {
            $entity = $definition->getEntityName();
            if ($entity !== ProductCrossSellingDefinition::ENTITY_NAME) {
                static::assertFalse(
                    $serializer->supports($definition->getEntityName()),
                    ProductCrossSellingSerializer::class . ' should not support ' . $entity
                );
            }
        }
    }

    public function testCrossSellingSerialize(): void
    {
        $crossSelling = $this->getProductCrossSelling();

        $assignedProductsRepository = static::getContainer()->get('product_cross_selling_assigned_products.repository');
        $productCrossSellingDefinition = static::getContainer()->get(ProductCrossSellingDefinition::class);

        $serializer = new ProductCrossSellingSerializer($assignedProductsRepository);
        $serializer->setRegistry(static::getContainer()->get(SerializerRegistry::class));

        $serialized = iterator_to_array($serializer->serialize(new Config([], [], []), $productCrossSellingDefinition, $crossSelling));

        static::assertNotEmpty($serialized);

        $assignedProducts = $crossSelling->getAssignedProducts();
        static::assertNotNull($assignedProducts);
        $assignedProducts->sort(fn (ProductCrossSellingAssignedProductsEntity $a, ProductCrossSellingAssignedProductsEntity $b) => $a->getPosition() <=> $b->getPosition());
        $productsIds = $assignedProducts->map(fn (ProductCrossSellingAssignedProductsEntity $assignedProductsEntity) => $assignedProductsEntity->getProductId());

        static::assertSame($crossSelling->getId(), $serialized['id']);
        static::assertSame($crossSelling->getProductId(), $serialized['productId']);
        static::assertSame(implode('|', $productsIds), $serialized['assignedProducts']);

        $deserialized = $serializer->deserialize(new Config([], [], []), $productCrossSellingDefinition, $serialized);

        static::assertIsArray($deserialized);
        static::assertSame($crossSelling->getId(), $deserialized['id']);
        static::assertSame($crossSelling->getProductId(), $deserialized['productId']);
        static::assertSame(array_values($productsIds), array_column($deserialized['assignedProducts'], 'productId'));
    }

    private function getProductCrossSelling(): ProductCrossSellingEntity
    {
        $ids = new IdsCollection();

        $data = [
            (new ProductBuilder($ids, 'a'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'b'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'c'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'd'))->price(15, 10)->visibility()->build(),
            (new ProductBuilder($ids, 'e'))->price(15, 10)->visibility()->build(),
        ];

        $productRepository = static::getContainer()->get('product.repository');
        $productRepository->create($data, Context::createDefaultContext());

        $crossSellingId = Uuid::randomHex();

        $crossSelling = [
            'id' => $crossSellingId,
            'productId' => $ids->get('a'),
            'active' => true,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'test cross selling',
                ],
            ],
            'type' => 'productList',
            'position' => 1,
            'limit' => 500,
            'sortBy' => 'name',
            'sortDirection' => 'ASC',
            'assignedProducts' => [
                ['productId' => $ids->get('b'), 'position' => 0],
                ['productId' => $ids->get('c'), 'position' => 1],
                ['productId' => $ids->get('d'), 'position' => 2],
                ['productId' => $ids->get('e'), 'position' => 3],
            ],
        ];

        /** @var EntityRepository<ProductCrossSellingCollection> $crossSellingRepository */
        $crossSellingRepository = static::getContainer()->get('product_cross_selling.repository');
        $crossSellingRepository->create([$crossSelling], Context::createDefaultContext());

        $criteria = new Criteria([$crossSellingId]);
        $criteria->addAssociation('assignedProducts');

        $crossSellingEntity = $crossSellingRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertNotNull($crossSellingEntity);

        return $crossSellingEntity;
    }
}

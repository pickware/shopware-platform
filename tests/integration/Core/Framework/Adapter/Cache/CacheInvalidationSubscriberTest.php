<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Adapter\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Property\PropertyGroupDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationSubscriber;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class CacheInvalidationSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private CacheInvalidator&MockObject $cacheInvalidatorMock;

    private CacheInvalidationSubscriber $cacheInvalidationSubscriber;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->cacheInvalidatorMock = $this->createMock(CacheInvalidator::class);
        $this->cacheInvalidationSubscriber = new CacheInvalidationSubscriber(
            $this->cacheInvalidatorMock,
            static::getContainer()->get(Connection::class),
            true
        );
    }

    public function testItInvalidatesCacheIfPropertyGroupIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = static::getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'sortingType' => PropertyGroupDefinition::SORTING_TYPE_POSITION,
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyGroupTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = static::getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'name' => 'new name',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfPropertyOptionIsAddedToGroup(): void
    {
        $this->insertDefaultPropertyGroup();

        $groupRepository = static::getContainer()->get('property_group.repository');
        $event = $groupRepository->update([
            [
                'id' => $this->ids->get('group1'),
                'options' => [
                    [
                        'id' => $this->ids->get('new-property'),
                        'name' => 'new-property',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyOptionIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-assigned'),
                'colorHexCode' => '#000000',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfUnassignedPropertyOptionIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-unassigned'),
                'colorHexCode' => '#000000',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItInvalidatesCacheIfPropertyOptionTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-assigned'),
                'name' => 'updated',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(1));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfUnassignedPropertyOptionTranslationIsChanged(): void
    {
        $this->insertDefaultPropertyGroup();

        $optionRepository = static::getContainer()->get('property_group_option.repository');
        $event = $optionRepository->update([
            [
                'id' => $this->ids->get('property-unassigned'),
                'name' => 'updated',
            ],
        ], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    public function testItDoesNotInvalidateCacheIfProductIsCreatedWithExistingOption(): void
    {
        $this->insertDefaultPropertyGroup();

        $builder = new ProductBuilder($this->ids, 'product2');
        $builder->price(10)
            ->property('property-assigned', '');

        $event = static::getContainer()->get('product.repository')->create([$builder->build()], Context::createDefaultContext());

        $this->cacheInvalidatorMock->expects($this->once())
            ->method('invalidate')
            ->with(static::countOf(0));

        $this->cacheInvalidationSubscriber->invalidatePropertyFilters($event);
    }

    private function insertDefaultPropertyGroup(): void
    {
        $groupRepository = static::getContainer()->get('property_group.repository');

        $data = [
            'id' => $this->ids->get('group1'),
            'name' => 'group1',
            'sortingType' => PropertyGroupDefinition::SORTING_TYPE_ALPHANUMERIC,
            'displayType' => PropertyGroupDefinition::DISPLAY_TYPE_TEXT,
            'options' => [
                [
                    'id' => $this->ids->get('property-assigned'),
                    'name' => 'assigned',
                ],
                [
                    'id' => $this->ids->get('property-unassigned'),
                    'name' => 'unassigned',
                ],
            ],
        ];

        $groupRepository->create([$data], Context::createDefaultContext());

        $builder = new ProductBuilder($this->ids, 'product1');
        $builder->price(10)
            ->property('property-assigned', '');

        static::getContainer()->get('product.repository')->create([$builder->build()], Context::createDefaultContext());
    }
}

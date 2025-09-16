<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DataAbstractionLayerException::class)]
class DataAbstractionLayerExceptionTest extends TestCase
{
    public function testInvalidCronIntervalFormat(): void
    {
        $e = DataAbstractionLayerException::invalidCronIntervalFormat('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_CRON_INTERVAL_CODE, $e->getErrorCode());
        static::assertSame('Unknown or bad CronInterval format "foo".', $e->getMessage());
    }

    public function testInvalidDateIntervalFormat(): void
    {
        $e = DataAbstractionLayerException::invalidDateIntervalFormat('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_DATE_INTERVAL_CODE, $e->getErrorCode());
        static::assertSame('Unknown or bad DateInterval format "foo".', $e->getMessage());
    }

    public function testInvalidSerializerField(): void
    {
        $e = DataAbstractionLayerException::invalidSerializerField(FkField::class, new IdField('foo', 'foo'));

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_FIELD_SERIALIZER_CODE, $e->getErrorCode());
    }

    public function testInvalidCriteriaIds(): void
    {
        $e = DataAbstractionLayerException::invalidCriteriaIds(['foo'], 'bar');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_CRITERIA_IDS, $e->getErrorCode());
    }

    public function testInvalidApiCriteriaIds(): void
    {
        $e = DataAbstractionLayerException::invalidApiCriteriaIds(
            DataAbstractionLayerException::invalidCriteriaIds(['foo'], 'bar')
        );

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_API_CRITERIA_IDS, $e->getErrorCode());
    }

    public function testInvalidLanguageId(): void
    {
        $e = DataAbstractionLayerException::invalidLanguageId('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_LANGUAGE_ID, $e->getErrorCode());
    }

    public function testInvalidFilterQuery(): void
    {
        $e = DataAbstractionLayerException::invalidFilterQuery('foo', 'baz');

        static::assertInstanceOf(InvalidFilterQueryException::class, $e);
        static::assertSame('foo', $e->getMessage());
        static::assertSame('baz', $e->getParameters()['path']);
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_FILTER_QUERY, $e->getErrorCode());
    }

    public function testInvalidSortQuery(): void
    {
        $e = DataAbstractionLayerException::invalidSortQuery('foo', 'baz');

        static::assertInstanceOf(InvalidSortQueryException::class, $e);
        static::assertSame('foo', $e->getMessage());
        static::assertSame('baz', $e->getParameters()['path']);
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::INVALID_SORT_QUERY, $e->getErrorCode());
    }

    public function testCannotCreateNewVersion(): void
    {
        $e = DataAbstractionLayerException::cannotCreateNewVersion('product', 'product-id');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('Cannot create new version. product by id product-id not found.', $e->getMessage());
        static::assertSame(DataAbstractionLayerException::CANNOT_CREATE_NEW_VERSION, $e->getErrorCode());
    }

    public function testVersionMergeAlreadyLocked(): void
    {
        $e = DataAbstractionLayerException::versionMergeAlreadyLocked('version-id');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::VERSION_MERGE_ALREADY_LOCKED, $e->getErrorCode());
        static::assertSame('Merging of version version-id is locked, as the merge is already running by another process.', $e->getMessage());
    }

    public function testExpectedArray(): void
    {
        $e = DataAbstractionLayerException::expectedArray('some/path/0');

        static::assertSame('Expected data at some/path/0 to be an array.', $e->getMessage());
        static::assertSame('some/path/0', $e->getParameters()['path']);
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__WRITE_MALFORMED_INPUT', $e->getErrorCode());
    }

    public function testExpectedAssociativeArray(): void
    {
        $e = DataAbstractionLayerException::expectedAssociativeArray('some/path/0');

        static::assertSame('Expected data at some/path/0 to be an associative array.', $e->getMessage());
        static::assertSame('some/path/0', $e->getParameters()['path']);
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__INVALID_WRITE_INPUT', $e->getErrorCode());
    }

    public function testDecodeHandledByHydrator(): void
    {
        $field = new ManyToManyAssociationField(
            'galleries',
            'MediaGallery',
            'MediaGalleryMapping',
            'media_id',
            'gallery_id',
        );

        $e = DataAbstractionLayerException::decodeHandledByHydrator($field);

        static::assertSame(
            \sprintf('Decoding of %s is handled by the entity hydrator.', ManyToManyAssociationField::class),
            $e->getMessage()
        );
        static::assertSame(ManyToManyAssociationField::class, $e->getParameters()['fieldClass']);
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::DECODE_HANDLED_BY_HYDRATOR, $e->getErrorCode());
    }

    public function testFkFieldByStorageNameNotFound(): void
    {
        $e = DataAbstractionLayerException::fkFieldByStorageNameNotFound(ProductDefinition::class, 'taxId');

        static::assertSame(
            'Can not detect FK field with storage name taxId in definition Shopware\Core\Content\Product\ProductDefinition',
            $e->getMessage()
        );

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND, $e->getErrorCode());
    }

    public function testLanguageFieldByStorageNameNotFound(): void
    {
        $e = DataAbstractionLayerException::languageFieldByStorageNameNotFound(ProductDefinition::class, 'taxId');

        static::assertSame(
            'Can not detect language field with storage name taxId in definition Shopware\Core\Content\Product\ProductDefinition',
            $e->getMessage()
        );
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND, $e->getErrorCode());
    }

    public function testDefinitionFieldDoesNotExist(): void
    {
        $e = DataAbstractionLayerException::definitionFieldDoesNotExist(ProductDefinition::class, 'taxId');

        static::assertSame(
            'Can not detect reference field with storage name taxId in definition Shopware\Core\Content\Product\ProductDefinition',
            $e->getMessage()
        );
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame(DataAbstractionLayerException::REFERENCE_FIELD_BY_STORAGE_NAME_NOT_FOUND, $e->getErrorCode());
    }

    public function testExpectedArrayWithType(): void
    {
        $path = 'includes';
        $type = 'string';

        $exception = DataAbstractionLayerException::expectedArrayWithType($path, $type);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(DataAbstractionLayerException::EXPECTED_ARRAY_WITH_TYPE, $exception->getErrorCode());
        static::assertSame(
            \sprintf('Expected data at %s to be of the type array, %s given', $path, $type),
            $exception->getMessage()
        );
        static::assertSame($path, $exception->getParameters()['path']);
        static::assertSame($type, $exception->getParameters()['type']);
    }

    public function testMissingFieldValue(): void
    {
        $field = new IdField('test_field', 'test_field');
        $exception = DataAbstractionLayerException::missingFieldValue($field);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(DataAbstractionLayerException::MISSING_FIELD_VALUE, $exception->getErrorCode());
        static::assertSame(
            'A value for the field "test_field" is required, but it is missing or `null`.',
            $exception->getMessage()
        );
    }

    public function testUnsupportedQueryFilter(): void
    {
        $exception = DataAbstractionLayerException::unsupportedQueryFilter('foo');

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(DataAbstractionLayerException::UNSUPPORTED_QUERY_FILTER, $exception->getErrorCode());
        static::assertSame('Unsupported query foo', $exception->getMessage());
    }

    public function testInvalidSortingDirection(): void
    {
        $e = DataAbstractionLayerException::invalidSortingDirection('foo');

        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame('FRAMEWORK__INVALID_SORT_DIRECTION', $e->getErrorCode());
        static::assertSame('The given sort direction "foo" is invalid.', $e->getMessage());
    }

    public function testConfigNotFound(): void
    {
        $e = DataAbstractionLayerException::configNotFound();

        static::assertSame('Configuration for product search definition not found', $e->getMessage());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame('FRAMEWORK__PRODUCT_SEARCH_CONFIGURATION_NOT_FOUND', $e->getErrorCode());
    }

    /**
     * @deprecated tag:v6.8.0 - will be removed. testConfigNotFound will cover the new behavior
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfigNotFoundDeprecated(): void
    {
        if (!\class_exists('\Shopware\Elasticsearch\Product\ElasticsearchProductException')) {
            static::markTestSkipped('\Shopware\Elasticsearch\Product\ElasticsearchProductException does not exist');
        }

        $e = DataAbstractionLayerException::configNotFound();

        static::assertSame('Configuration for product elasticsearch definition not found', $e->getMessage());
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $e->getStatusCode());
        static::assertSame('ELASTICSEARCH_PRODUCT__CONFIGURATION_NOT_FOUND', $e->getErrorCode());
    }
}

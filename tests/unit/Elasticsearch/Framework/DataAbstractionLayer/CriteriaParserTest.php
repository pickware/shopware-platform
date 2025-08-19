<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearchDSL\Aggregation\Bucketing\CompositeAggregation;
use OpenSearchDSL\Sort\FieldSort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\System\CustomField\CustomFieldService;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;
use Shopware\Core\System\Unit\UnitDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\Adapter\Storage\ArrayKeyValueStorage;
use Shopware\Elasticsearch\ElasticsearchException;
use Shopware\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Shopware\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CriteriaParser::class)]
class CriteriaParserTest extends TestCase
{
    private const SECOND_LANGUAGE = 'd5da80fc94874ea988eac8abdea44e0a';

    public function testAggregationWithSorting(): void
    {
        $aggs = new TermsAggregation('foo', 'test', null, new FieldSorting('abc', FieldSorting::ASCENDING), new TermsAggregation('foo', 'foo2'));

        $definition = $this->getDefinition();

        /** @var CompositeAggregation $esAgg */
        $esAgg = (new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $this->createMock(CustomFieldService::class),
            new ArrayKeyValueStorage([]),
        ))->parseAggregation($aggs, $definition, Context::createDefaultContext());

        static::assertInstanceOf(CompositeAggregation::class, $esAgg);
        static::assertSame([
            'composite' => [
                'sources' => [
                    [
                        'foo.sorting' => [
                            'terms' => [
                                'field' => 'abc',
                                'order' => 'ASC',
                            ],
                        ],
                    ],
                    [
                        'foo.key' => [
                            'terms' => [
                                'field' => 'test',
                            ],
                        ],
                    ],
                ],
                'size' => 10000,
            ],
            'aggregations' => [
                'foo' => [
                    'terms' => [
                        'field' => 'foo2',
                        'size' => 10000,
                    ],
                ],
            ],
        ], $esAgg->toArray());
    }

    public function testParseAggregationWithTranslatedField(): void
    {
        $aggs = new TermsAggregation('byName', 'name');

        $definition = $this->getDefinition();

        $parser = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $this->createMock(CustomFieldService::class),
            new ArrayKeyValueStorage([]),
        );

        $esAgg = $parser->parseAggregation($aggs, $definition, Context::createDefaultContext());

        static::assertInstanceOf(\OpenSearchDSL\Aggregation\Bucketing\TermsAggregation::class, $esAgg);
        static::assertSame([
            'terms' => [
                'field' => 'name.' . Defaults::LANGUAGE_SYSTEM,
                'size' => 10000,
            ],
        ], $esAgg->toArray());
    }

    /**
     * @param array<mixed> $expectedEsStats
     */
    #[DataProvider('parseStatsDataProvider')]
    public function testParseStatsAggregation(string $fieldName, array $expectedEsStats): void
    {
        $aggs = new StatsAggregation('fooStats', $fieldName);

        $definition = $this->getDefinition();

        $parser = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $this->createMock(CustomFieldService::class),
            new ArrayKeyValueStorage([]),
        );

        $esAgg = $parser->parseAggregation($aggs, $definition, Context::createDefaultContext());

        static::assertInstanceOf(\OpenSearchDSL\Aggregation\Metric\StatsAggregation::class, $esAgg);
        static::assertSame($expectedEsStats, $esAgg->toArray());
    }

    /**
     * @param array<mixed> $expectedEsFilter
     */
    #[DataProvider('parseFilterDataProvider')]
    public function testParseFilter(Filter $filter, array $expectedEsFilter): void
    {
        $definition = $this->getDefinition();

        $parser = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $this->createMock(CustomFieldService::class),
            new ArrayKeyValueStorage([
                ElasticsearchOptimizeSwitch::FLAG => true,
            ]),
        );

        $context = new Context(
            new SystemSource(),
            [],
            Defaults::CURRENCY,
            [self::SECOND_LANGUAGE, Defaults::LANGUAGE_SYSTEM]
        );

        $esFilter = $parser->parseFilter($filter, $definition, ProductDefinition::ENTITY_NAME, $context);
        static::assertSame($expectedEsFilter, $esFilter->toArray());
    }

    public function testParseUnsupportedFilter(): void
    {
        $definition = $this->getDefinition();

        $parser = new CriteriaParser(new EntityDefinitionQueryHelper(), $this->createMock(CustomFieldService::class), new ArrayKeyValueStorage([]));

        static::expectException(ElasticsearchException::class);
        static::expectExceptionMessage(\sprintf('Provided filter of class %s is not supported', CustomFilter::class));
        $parser->parseFilter(new CustomFilter(), $definition, ProductDefinition::ENTITY_NAME, Context::createDefaultContext());
    }

    #[DataProvider('accessorContextProvider')]
    public function testBuildAccessor(string $field, Context $context, string $expectedAccessor): void
    {
        $definition = $this->getDefinition();

        $accessor = (new CriteriaParser(new EntityDefinitionQueryHelper(), $this->createMock(CustomFieldService::class), new ArrayKeyValueStorage([])))->buildAccessor($definition, $field, $context);

        static::assertSame($expectedAccessor, $accessor);
    }

    /**
     * @return iterable<string, array{string, array<mixed>}>
     */
    public static function parseStatsDataProvider(): iterable
    {
        yield 'cheapest_price stats aggregation' => [
            'cheapestPrice',
            [
                'stats' => [
                    'script' => [
                        'source' => <<<EOT
double getPrice(def accessors, def doc, def decimals, def round, def multiplier) {
    for (accessor in accessors) {
        def key = accessor['key'];\n
        if (!doc.containsKey(key) || doc[key].empty) {
            continue;
        }\n
        def factor = accessor['factor'];
        def value = doc[key].value * factor;\n
        value = Math.round(value * decimals);
        value = (double) value / decimals;\n
        if (!round) {
            return (double) value;
        }\n
        value = Math.round(value * multiplier);\n
        value = (double) value / multiplier;\n
        return (double) value;
    }\n
    return 0;
}\n
return getPrice(params['accessors'], doc, params['decimals'], params['round'], params['multiplier']);\n
EOT,
                        'lang' => 'painless',
                        'params' => [
                            'accessors' => [
                                [
                                    'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                                    'factor' => 1,
                                ],
                            ],
                            'decimals' => 100,
                            'round' => true,
                            'multiplier' => 100.0,
                        ],
                    ],
                ],
            ],
        ];

        yield 'cheapest_price_percentage stats aggregation' => [
            'cheapestPrice.percentage',
            [
                'stats' => [
                    'script' => [
                        'source' => <<<EOT
double getPercentage(def accessors, def doc) {
    for (accessor in accessors) {
        def key = accessor['key'];
        if (!doc.containsKey(key) || doc[key].empty) {
            continue;
        }\n
        return (double) doc[key].value;
    }\n
    return 0;
}\n
return getPercentage(params['accessors'], doc);\n
EOT,
                        'lang' => 'painless',
                        'params' => [
                            'accessors' => [
                                [
                                    'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                                    'factor' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'other field stats aggregation' => [
            'name',
            [
                'stats' => [
                    'field' => 'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, Filter|array<mixed>>
     */
    public static function parseFilterDataProvider(): iterable
    {
        $now = '2023-06-12 05:36:22.000';

        yield 'NotFilter field' => [
            new NotFilter('AND', [
                new EqualsFilter('id', 'foo'),
                new EqualsFilter('productNumber', 'bar'),
            ]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'id' => 'foo',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'productNumber' => 'bar',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'NotFilter translated field' => [
            new NotFilter('AND', [
                new EqualsFilter('name', 'foo'),
                new EqualsFilter('description', 'bar'),
            ]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'name.' . self::SECOND_LANGUAGE => 'foo',
                                        ],
                                    ],
                                    [
                                        'multi_match' => [
                                            'query' => 'bar',
                                            'fields' => [
                                                'description.' . self::SECOND_LANGUAGE,
                                                'description.' . Defaults::LANGUAGE_SYSTEM,
                                            ],
                                            'type' => 'best_fields',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'MultiFilter field' => [
            new MultiFilter('AND', [
                new EqualsFilter('id', 'foo'),
                new EqualsFilter('productNumber', 'bar'),
            ]),
            [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'id' => 'foo',
                            ],
                        ],
                        [
                            'term' => [
                                'productNumber' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'MultiFilter translated field' => [
            new MultiFilter('AND', [
                new EqualsFilter('name', 'foo'),
                new EqualsFilter('description', 'bar'),
            ]),
            [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'name.' . self::SECOND_LANGUAGE => 'foo',
                            ],
                        ],
                        [
                            'multi_match' => [
                                'query' => 'bar',
                                'fields' => [
                                    'description.' . self::SECOND_LANGUAGE,
                                    'description.' . Defaults::LANGUAGE_SYSTEM,
                                ],
                                'type' => 'best_fields',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'EqualsFilter field' => [
            new EqualsFilter('productNumber', 'bar'),
            [
                'term' => [
                    'productNumber' => 'bar',
                ],
            ],
        ];
        yield 'EqualsFilter name translated field' => [
            new EqualsFilter('name', 'foo'),
            [
                'term' => [
                    'name.' . self::SECOND_LANGUAGE => 'foo',
                ],
            ],
        ];

        yield 'EqualsFilter description translated field' => [
            new EqualsFilter('description', 'foo'),
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'description.' . self::SECOND_LANGUAGE,
                        'description.' . Defaults::LANGUAGE_SYSTEM,
                    ],
                    'type' => 'best_fields',
                ],
            ],
        ];

        yield 'EqualsAnyFilter field' => [
            new EqualsAnyFilter('productNumber', ['foo', 'bar']),
            [
                'terms' => [
                    'productNumber' => ['foo', 'bar'],
                ],
            ],
        ];

        yield 'EqualsAnyFilter name translated field' => [
            new EqualsAnyFilter('name', ['foo', 'bar']),
            [
                'terms' => [
                    'name.' . self::SECOND_LANGUAGE => ['foo', 'bar'],
                ],
            ],
        ];

        yield 'EqualsAnyFilter description translated field' => [
            new EqualsAnyFilter('description', ['foo', 'bar']),
            [
                'dis_max' => [
                    'queries' => [
                        [
                            'terms' => [
                                'description.' . self::SECOND_LANGUAGE => ['foo', 'bar'],
                            ],
                        ],
                        [
                            'terms' => [
                                'description.' . Defaults::LANGUAGE_SYSTEM => ['foo', 'bar'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'EqualsAnyFilter field with null' => [
            new EqualsAnyFilter('productNumber', ['foo', 'bar', null]),
            [
                'bool' => [
                    'should' => [
                        [
                            'terms' => [
                                'productNumber' => ['foo', 'bar'],
                            ],
                        ],
                        [
                            'bool' => [
                                'must_not' => [
                                    [
                                        'exists' => [
                                            'field' => 'productNumber',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'EqualsAnyFilter name translated field with null' => [
            new EqualsAnyFilter('name', ['foo', 'bar', null]),
            [
                'bool' => [
                    'should' => [
                        [
                            'terms' => [
                                'name.' . self::SECOND_LANGUAGE => ['foo', 'bar'],
                            ],
                        ],
                        [
                            'bool' => [
                                'must_not' => [
                                    [
                                        'exists' => [
                                            'field' => 'name.' . self::SECOND_LANGUAGE,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'ContainsFilter field' => [
            new ContainsFilter('productNumber', 'foo'),
            [
                'wildcard' => [
                    'productNumber' => [
                        'value' => '*foo*',
                    ],
                ],
            ],
        ];
        yield 'ContainsFilter name translated field' => [
            new ContainsFilter('name', 'foo'),
            [
                'wildcard' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        'value' => '*foo*',
                    ],
                ],
            ],
        ];
        yield 'PrefixFilter field' => [
            new PrefixFilter('productNumber', 'foo'),
            [
                'prefix' => [
                    'productNumber' => [
                        'value' => 'foo',
                    ],
                ],
            ],
        ];
        yield 'PrefixFilter name translated field' => [
            new PrefixFilter('name', 'foo'),
            [
                'prefix' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        'value' => 'foo',
                    ],
                ],
            ],
        ];
        yield 'SuffixFilter field' => [
            new SuffixFilter('productNumber', 'foo'),
            [
                'wildcard' => [
                    'productNumber' => [
                        'value' => '*foo',
                    ],
                ],
            ],
        ];
        yield 'SuffixFilter name translated field' => [
            new SuffixFilter('name', 'foo'),
            [
                'wildcard' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        'value' => '*foo',
                    ],
                ],
            ],
        ];
        yield 'RangeFilter field' => [
            new RangeFilter('createdAt', [
                RangeFilter::GT => $now,
            ]),
            [
                'range' => [
                    'createdAt' => [
                        RangeFilter::GT => $now,
                    ],
                ],
            ],
        ];
        yield 'RangeFilter name translated field' => [
            new RangeFilter('name', [
                RangeFilter::GT => $now,
            ]),
            [
                'range' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        RangeFilter::GT => $now,
                    ],
                ],
            ],
        ];

        yield 'EqualsFilter translated custom field' => [
            new EqualsFilter('customFields.foo', null),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'exists' => ['field' => 'customFields.' . self::SECOND_LANGUAGE . '.foo'],
                        ],
                        [
                            'exists' => ['field' => 'customFields.' . Defaults::LANGUAGE_SYSTEM . '.foo'],
                        ],
                    ],
                ],
            ],
        ];

        yield 'MultiFilter with translated custom field' => [
            new MultiFilter('AND', [
                new EqualsFilter('customFields.foo', 'fooValue'),
                new EqualsFilter('customFields.bar', 'barValue'),
            ]),
            [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => 'fooValue',
                                'fields' => [
                                    'customFields.' . self::SECOND_LANGUAGE . '.foo',
                                    'customFields.' . Defaults::LANGUAGE_SYSTEM . '.foo',
                                ],
                                'type' => 'best_fields',
                            ],
                        ],
                        [
                            'multi_match' => [
                                'query' => 'barValue',
                                'fields' => [
                                    'customFields.' . self::SECOND_LANGUAGE . '.bar',
                                    'customFields.' . Defaults::LANGUAGE_SYSTEM . '.bar',
                                ],
                                'type' => 'best_fields',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'EqualsFilter cheapestPrice.percentage field' => [
            new EqualsFilter('product.cheapestPrice.percentage', 10),
            [
                'script' => [
                    'script' => [
                        'inline' => <<<EOT
String getPercentageKey(def accessors, def doc) {
    for (accessor in accessors) {
        def key = accessor['key'];
        if (!doc.containsKey(key) || doc[key].empty) {
            continue;
        }

        return key;
    }

    return '';
}

def percentageKey = getPercentageKey(params['accessors'], doc);

if (percentageKey == '') {
    if (params.containsKey('eq') && params['eq'] === null) {
        return true;
    }

    return false;
}

def percentage = (double) doc[percentageKey].value;

def match = true;
if (params.containsKey('eq')) {
    match = match && percentage == params['eq'];
}
if (params.containsKey('gte')) {
    match = match && percentage >= params['gte'];
}
if (params.containsKey('gt')) {
    match = match && percentage > params['gt'];
}
if (params.containsKey('lte')) {
    match = match && percentage <= params['lte'];
}
if (params.containsKey('lt')) {
    match = match && percentage < params['lt'];
}

return match;

EOT,
                        'params' => [
                            'eq' => 10.0,
                            'accessors' => [
                                [
                                    'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                                    'factor' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, array{string, Context, string}>
     */
    public static function accessorContextProvider(): iterable
    {
        yield 'normal field' => [
            'foo',
            Context::createDefaultContext(),
            'foo',
        ];

        yield 'price, state from field: gross' => [
            'price.foo.gross',
            Context::createDefaultContext(),
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.gross',
        ];

        yield 'price, state from field: net' => [
            'price.foo.net',
            Context::createDefaultContext(),
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.net',
        ];

        yield 'price, state inherited from context: gross' => [
            'price.foo',
            Context::createDefaultContext(),
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.gross',
        ];

        $stateNet = Context::createDefaultContext();
        $stateNet->setTaxState(CartPrice::TAX_STATE_NET);

        yield 'price, state inherited from context: net' => [
            'price.foo',
            $stateNet,
            'price.foo.c_b7d2554b0ce847cd82f3ac9bd1c0dfca.net',
        ];
    }

    /**
     * @param array<mixed> $expectedQuery
     */
    #[DataProvider('providerCheapestPrice')]
    public function testCheapestPriceSorting(FieldSorting $sorting, array $expectedQuery, Context $context): void
    {
        $this->executeCheapestPriceTest($sorting, $expectedQuery, $context, true);
    }

    #[DataProvider('providerTranslatedField')]
    public function testTranslatedFieldSorting(FieldSorting $sorting, FieldSort $expectedFieldSort, ?Field $customField = null): void
    {
        $definition = $this->getDefinition(ProductManufacturerDefinition::ENTITY_NAME);

        $customFieldService = $this->createMock(CustomFieldService::class);

        if ($customField instanceof Field) {
            $customFieldService->method('getCustomField')->willReturn($customField);
        }

        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [
                Defaults::LANGUAGE_SYSTEM,
                self::SECOND_LANGUAGE,
            ],
        ]);

        $fieldSort = (new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $customFieldService,
            new ArrayKeyValueStorage([
                ElasticsearchOptimizeSwitch::FLAG => true,
            ]),
        ))->parseSorting($sorting, $definition, $context);

        static::assertSame($expectedFieldSort->getField(), $fieldSort->getField());
        static::assertNotNull($expectedFieldSort->getOrder());
        static::assertNotNull($fieldSort->getOrder());
        static::assertSame(strtolower($expectedFieldSort->getOrder()), strtolower($fieldSort->getOrder()));
        static::assertSame($expectedFieldSort->getParameters(), $fieldSort->getParameters());
    }

    /**
     * @return iterable<string, array{FieldSorting, array<mixed>, Context}>
     */
    public static function providerCheapestPrice(): iterable
    {
        yield 'default cheapest price' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'lang' => 'painless',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => true,
                    'multiplier' => 100.0,
                ],
            ],
            Context::createDefaultContext(),
        ];

        yield 'default cheapest price/list price percentage' => [
            new FieldSorting('cheapestPrice.percentage', FieldSorting::ASCENDING),
            [
                'lang' => 'painless',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                            'factor' => 1,
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext(),
        ];

        $context = Context::createDefaultContext();
        $context->assign(['currencyId' => 'foo']);

        yield 'different currency cheapest price' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'lang' => 'painless',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyfoo_gross',
                            'factor' => 1,
                        ],
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1.0,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => true,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];

        yield 'different currency cheapest price/list price percentage' => [
            new FieldSorting('cheapestPrice.percentage', FieldSorting::ASCENDING),
            [
                'lang' => 'painless',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyfoo_gross_percentage',
                            'factor' => 1,
                        ],
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                            'factor' => 1.0,
                        ],
                    ],
                ],
            ],
            $context,
        ];

        $context = Context::createDefaultContext();
        $context->getRounding()->setDecimals(3);

        yield 'default cheapest price: rounding with 3 decimals' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'lang' => 'painless',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 1000,
                    'round' => false,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];

        $context = Context::createDefaultContext();
        $context->assign(['taxState' => CartPrice::TAX_STATE_NET]);
        $context->getRounding()->setRoundForNet(false);

        yield 'default cheapest price: net rounding disabled' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'lang' => 'painless',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_net',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => false,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];
    }

    /**
     * @return iterable<string, array{FieldSorting, FieldSort, ?Field}>
     */
    public static function providerTranslatedField(): iterable
    {
        yield 'non translated field' => [
            new FieldSorting('productNumber', FieldSorting::ASCENDING),
            new FieldSort('productNumber', FieldSorting::ASCENDING, null, []),
            null,
        ];

        yield 'customFields translated field' => [
            new FieldSorting('customFields.foo', FieldSorting::DESCENDING),
            new FieldSort('_script', FieldSorting::DESCENDING, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'foo',
                    ],
                ],
            ]),
            new StringField('foo', 'foo'),
        ];

        yield 'customFields int translated field' => [
            new FieldSorting('customFields.foo', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSorting::ASCENDING, null, [
                'type' => 'number',
                'script' => [
                    'source' => static::getScriptIntSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'foo',
                        'order' => FieldSort::ASC,
                    ],
                ],
            ]),
            new IntField('foo', 'foo'),
        ];

        yield 'customFields float translated field' => [
            new FieldSorting('customFields.foo', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, null, [
                'type' => 'number',
                'script' => [
                    'source' => static::getScriptIntSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'foo',
                        'order' => FieldSort::ASC,
                    ],
                ],
            ]),
            new FloatField('foo', 'foo'),
        ];

        yield 'non nested translated field' => [
            new FieldSorting('name', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'name',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                    ],
                ],
            ]),
            null,
        ];

        yield 'non translated field with root prefix' => [
            new FieldSorting('product_manufacturer.name', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'name',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                    ],
                ],
            ]),
            null,
        ];

        yield 'nested translated field' => [
            new FieldSorting('product_manufacturer.products.name', FieldSorting::ASCENDING),
            new FieldSort('products.name.' . Defaults::LANGUAGE_SYSTEM, FieldSorting::ASCENDING, null, ['nested' => ['path' => 'products']]),
            null,
        ];

        yield 'nested translated field with root prefix' => [
            new FieldSorting('products.name', FieldSorting::ASCENDING),
            new FieldSort('products.name.' . Defaults::LANGUAGE_SYSTEM, FieldSorting::ASCENDING, null, ['nested' => ['path' => 'products']]),
            null,
        ];

        yield 'customFields string translated field in descending order' => [
            new FieldSorting('customFields.bar', FieldSorting::DESCENDING),
            new FieldSort('_script', FieldSort::DESC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'bar',
                    ],
                ],
            ]),
            new StringField('bar', 'bar'),
        ];

        yield 'customFields bool translated field' => [
            new FieldSorting('customFields.boolField', FieldSorting::DESCENDING),
            new FieldSort('_script', FieldSort::DESC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'boolField',
                    ],
                ],
            ]),
            new BoolField('boolField', 'boolField'),
        ];
    }

    /**
     * @param array<mixed> $expectedFilter
     */
    #[DataProvider('providerFilter')]
    public function testFilterParsing(Filter $filter, array $expectedFilter): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->getDefinition();

        $sortedFilter = (new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $this->createMock(CustomFieldService::class),
            new ArrayKeyValueStorage([]),
        ))->parseFilter($filter, $definition, '', $context);

        $sortedFilterArray = $sortedFilter->toArray();

        // Unset the 'source' key before comparison.
        unset($sortedFilterArray['script']['script']['inline']);

        static::assertEquals($expectedFilter, $sortedFilterArray);
    }

    /**
     * @return iterable<string, array{Filter, array<mixed>}>
     */
    public static function providerFilter(): iterable
    {
        yield 'not filter: and' => [
            new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'test' => 'value',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'test2' => 'value',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'not filter: or' => [
            new NotFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'term' => [
                                            'test' => 'value',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'test2' => 'value',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'not filter: xor' => [
            new NotFilter(MultiFilter::CONNECTION_XOR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'bool' => [
                                            'must' => [
                                                [
                                                    'term' => [
                                                        'test' => 'value',
                                                    ],
                                                ],
                                            ],
                                            'must_not' => [
                                                [
                                                    'term' => [
                                                        'test2' => 'value',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'bool' => [
                                            'must_not' => [
                                                [
                                                    'term' => [
                                                        'test' => 'value',
                                                    ],
                                                ],
                                            ],
                                            'must' => [
                                                [
                                                    'term' => [
                                                        'test2' => 'value',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'range filter: cheapestPrice' => [
            new RangeFilter('cheapestPrice', [RangeFilter::GTE => 10]),
            [
                'script' => [
                    'script' => [
                        'params' => [
                            RangeFilter::GTE => 10,
                            'accessors' => [
                                [
                                    'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                                    'factor' => 1,
                                ],
                            ],
                            'decimals' => 100,
                            'round' => true,
                            'multiplier' => 100.0,
                        ],
                    ],
                ],
            ],
        ];

        yield 'range filter: cheapestPrice price/list price percentage' => [
            new RangeFilter('cheapestPrice.percentage', [RangeFilter::GTE => 10]),
            [
                'script' => [
                    'script' => [
                        'params' => [
                            RangeFilter::GTE => 10,
                            'accessors' => [
                                [
                                    'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                                    'factor' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'range filter: datetime' => [
            new RangeFilter('createdAt', [RangeFilter::GTE => '2023-06-01', RangeFilter::LT => '2023-06-03 13:47:42.759']),
            [
                'range' => [
                    'createdAt' => [
                        'gte' => '2023-06-01 00:00:00.000',
                        'lt' => '2023-06-03 13:47:42.000',
                    ],
                ],
            ],
        ];

        yield 'translated property of related entity with a name that doesn\'t exist in the product definition' => [
            new EqualsFilter('unit.shortCode', 'value'),
            [
                'nested' => [
                    'path' => 'unit',
                    'query' => [
                        'term' => [
                            'unit.shortCode.2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'value',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getDefinition(string $entityName = 'product'): EntityDefinition
    {
        $instanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                ProductDefinition::class,
                ProductManufacturerDefinition::class,
                ProductManufacturerTranslationDefinition::class,
                UnitDefinition::class,
                ProductTranslationDefinition::class,
                UnitTranslationDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        return $instanceRegistry->getByEntityName($entityName);
    }

    /**
     * @param array<mixed> $expectedQuery
     */
    #[DataProvider('providerCheapestPrice')]
    public function testCheapestPriceSortingSourceExists(
        FieldSorting $sorting,
        array $expectedQuery,
        Context $context
    ): void {
        $definition = $this->getDefinition();

        $sorting = (new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $this->createMock(CustomFieldService::class),
            new ArrayKeyValueStorage([]),
        ))->parseSorting($sorting, $definition, $context);

        $script = $sorting->getParameter('script');

        static::assertIsArray($script);
        static::assertArrayHasKey('source', $script);
        static::assertNotEmpty($script['source']);
    }

    /**
     * @return iterable<string, array{FieldSorting, array<mixed>, Context}>
     */
    public static function providerOldFeatureVersion(): iterable
    {
        yield 'default cheapest price' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => true,
                    'multiplier' => 100.0,
                ],
            ],
            Context::createDefaultContext(),
        ];

        yield 'default cheapest price/list price percentage' => [
            new FieldSorting('cheapestPrice.percentage', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price_percentage',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                            'factor' => 1,
                        ],
                    ],
                ],
            ],
            Context::createDefaultContext(),
        ];

        $context = Context::createDefaultContext();
        $context->assign(['currencyId' => 'foo']);

        yield 'different currency cheapest price' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyfoo_gross',
                            'factor' => 1,
                        ],
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1.0,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => true,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];

        yield 'different currency cheapest price/list price percentage' => [
            new FieldSorting('cheapestPrice.percentage', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price_percentage',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyfoo_gross_percentage',
                            'factor' => 1,
                        ],
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross_percentage',
                            'factor' => 1.0,
                        ],
                    ],
                ],
            ],
            $context,
        ];

        $context = Context::createDefaultContext();
        $context->getRounding()->setDecimals(3);

        yield 'default cheapest price: rounding with 3 decimals' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_gross',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 1000,
                    'round' => false,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];

        $context = Context::createDefaultContext();
        $context->assign(['taxState' => CartPrice::TAX_STATE_NET]);
        $context->getRounding()->setRoundForNet(false);

        yield 'default cheapest price: net rounding disabled' => [
            new FieldSorting('cheapestPrice', FieldSorting::ASCENDING),
            [
                'id' => 'cheapest_price',
                'params' => [
                    'accessors' => [
                        [
                            'key' => 'cheapest_price_ruledefault_currencyb7d2554b0ce847cd82f3ac9bd1c0dfca_net',
                            'factor' => 1,
                        ],
                    ],
                    'decimals' => 100,
                    'round' => false,
                    'multiplier' => 100.0,
                ],
            ],
            $context,
        ];
    }

    public static function getScriptStringSortingSource(): string
    {
        return 'def languages = params[\'languages\'];
def suffix = params.containsKey(\'suffix\') ? \'.\' + params[\'suffix\'] : \'\';

for (int i = 0; i < languages.length; i++) {
    def field_name = params[\'field\'] + \'.\' + languages[i] + suffix;

    if (doc[field_name].size() > 0 && doc[field_name].value != null && doc[field_name].value.toString().length() > 0) {
        def fieldValue = doc[field_name].value;

        return fieldValue.toString();
    }
}

return \'\';
';
    }

    public static function getScriptIntSortingSource(): string
    {
        return 'def languages = params[\'languages\'];
def suffix = params.containsKey(\'suffix\') ? \'.\' + params[\'suffix\'] : \'\';

for (int i = 0; i < languages.length; i++) {
    def field_name = params[\'field\'] + \'.\' + languages[i] + suffix;

    if (doc[field_name].size() > 0 && doc[field_name].value != null && doc[field_name].value.toString().length() > 0) {
        def fieldValue = doc[field_name].value;

        return fieldValue;
    }
}

if (params[\'order\'] == \'asc\') {
    return Double.MAX_VALUE;
}

return Double.MIN_VALUE;
';
    }

    /**
     * @param array<string, mixed> $expectedQuery
     */
    private function executeCheapestPriceTest(FieldSorting $sorting, array $expectedQuery, Context $context, bool $unsetSource = false): void
    {
        $definition = $this->getDefinition();

        $parsedSorting = (new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $this->createMock(CustomFieldService::class),
            new ArrayKeyValueStorage([]),
        ))->parseSorting($sorting, $definition, $context);

        $script = $parsedSorting->getParameter('script');
        static::assertIsArray($script);

        if ($unsetSource) {
            unset($script['source']);
        }

        static::assertSame($expectedQuery, $script);
    }
}

/**
 * @internal
 */
class CustomFilter extends Filter
{
    public function getFields(): array
    {
        return [];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SearchKeyword;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SearchKeyword\AnalyzedKeyword;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchKeywordAnalyzer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;

/**
 * @internal
 */
#[CoversClass(ProductSearchKeywordAnalyzer::class)]
class ProductSearchKeywordAnalyzerTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @param array<string, mixed> $productData
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     * @param array<int, string> $expected
     */
    #[DataProvider('analyzeCases')]
    public function testAnalyze(array $productData, array $configFields, array $expected): void
    {
        $product = new ProductEntity();
        $product->assign($productData);

        $tokenizer = new Tokenizer(3, ['-', '_']);
        $tokenFilter = $this->createMock(TokenFilter::class);
        $tokenFilter->method('filter')->willReturnCallback(fn (array $tokens) => $tokens);

        $configLoader = $this->createMock(SearchConfigLoader::class);
        $configLoader->method('load')
            ->willReturn([
                [
                    'min_search_length' => 3,
                ],
            ]);

        $analyzer = new ProductSearchKeywordAnalyzer($tokenizer, $tokenFilter, $configLoader);

        $analyzer = $analyzer->analyze($product, $this->context, $configFields);
        $analyzerResult = $analyzer->getKeys();

        sort($analyzerResult);
        sort($expected);

        static::assertEquals($expected, $analyzerResult);
    }

    /**
     * The old implementation relied on the error_reporting level, to also report notices as errors.
     * This test ensures that the new implementation does not rely on the error_reporting level.
     *
     * @param array<string, mixed> $productData
     * @param array<int, array{field: string, tokenize: bool, ranking: int}> $configFields
     * @param array<int, string> $expected
     */
    #[DataProvider('analyzeCases')]
    public function testAnalyzeWithIgnoredErrorNoticeReporting(array $productData, array $configFields, array $expected): void
    {
        $oldLevel = error_reporting(\E_ERROR);

        $this->testAnalyze($productData, $configFields, $expected);

        error_reporting($oldLevel);
    }

    /**
     * @return iterable<string, array{0:array<string, array<string, string|array<int|string, string|array<int|string>>>|int|string|TagCollection>, 1:array<int, array{field: string, tokenize: bool, ranking: int}>, 2:array<int, int|string>}>
     */
    public static function analyzeCases(): iterable
    {
        $tag1 = new TagEntity();
        $tag1->setId('tag-1');
        $tag1->setName('Tag Yellow');

        $tag2 = new TagEntity();
        $tag2->setId('tag-2');
        $tag2->setName('Tag Pink');

        $tags = new TagCollection([$tag1, $tag2]);

        yield 'analyze with tokenize' => [
            [
                'maxPurchase' => 20,
                'manufacturerNumber' => 'MANU_001',
                'description' => self::getLongTextDescription(),
                'tags' => $tags,
                'translated' => [
                    'name' => 'Awesome product',
                ],
            ],
            [
                [
                    'field' => 'maxPurchase',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'description',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'tags.name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'manufacturerNumber',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
            ],
            [
                '20',
                'awesome',
                'awesome product',
                'description',
                'long',
                'manu_001',
                'pink',
                'product',
                'tag',
                'tag yellow pink',
                'this',
                'this long description',
                'yellow',
            ],
        ];

        yield 'analyze without tokenize' => [
            [
                'maxPurchase' => 20,
                'manufacturerNumber' => 'MANU_001',
                'description' => self::getLongTextDescription(),
                'tags' => $tags,
                'translated' => [
                    'name' => 'Awesome product',
                ],
            ],
            [
                [
                    'field' => 'maxPurchase',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'description',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'tags.name',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'manufacturerNumber',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
            ],
            [
                20,
                'MANU_001',
                'Tag Pink',
                'Tag Yellow',
                self::getLongTextPart1(),
                self::getLongTextPart2(),
                'awesome',
                'product',
                'awesome product',
            ],
        ];

        yield 'analyze nested array field' => [
            [
                'customFields' => [
                    'flat' => [
                        'part-a', 'part-b',
                    ],
                    'nested' => [
                        'part-a' => ['a1', 'a2'], 'part-b' => ['b1', 'b2'],
                    ],
                    'nested-with-long-desc' => [
                        'part-a' => [self::getLongTextDescription()],
                    ],
                ],
                'translated' => [
                    'name' => 'Awesome product',
                ],
            ],
            [
                [
                    'field' => 'customFields.flat',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'customFields.nested',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
                [
                    'field' => 'nested-with-long-desc',
                    'tokenize' => false,
                    'ranking' => 100,
                ],
                [
                    'field' => 'name',
                    'tokenize' => true,
                    'ranking' => 100,
                ],
            ],
            [
                'awesome',
                'part-a',
                'part-b',
                'product',
                'awesome product',
                'part-a part-b',
            ],
        ];
    }

    public function testAssociativeArrayOrderIndependence(): void
    {
        $tokenizer = $this->createMock(TokenizerInterface::class);
        $tokenizer->method('tokenize')
            ->with('value1 value2 value3', 3)
            ->willReturnCallback(function (string $text) {
                return explode(' ', $text);
            });

        $tokenFilter = $this->createMock(AbstractTokenFilter::class);
        $tokenFilter->method('filter')
            ->willReturnArgument(0);

        $configLoader = $this->createMock(SearchConfigLoader::class);
        $configLoader->method('load')
            ->willReturn([
                [
                    'min_search_length' => 3,
                ],
            ]);

        $analyzer = new ProductSearchKeywordAnalyzer(
            $tokenizer,
            $tokenFilter,
            $configLoader
        );

        $config = [
            [
                'field' => 'customFields.assocArray',
                'tokenize' => true,
                'ranking' => 100,
            ],
        ];

        // Test with first order of keys
        $product1 = new ProductEntity();
        $product1->setCustomFields([
            'assocArray' => [
                'key1' => 'value1',
                'key2' => 'value2',
                'key3' => 'value3',
            ],
        ]);

        $result1 = $analyzer->analyze($product1, Context::createDefaultContext(), $config);
        $words1 = $result1->map(fn (AnalyzedKeyword $keyword) => $keyword->getKeyword());
        sort($words1);

        // Test with different order of keys
        $product2 = new ProductEntity();
        $product2->setCustomFields([
            'assocArray' => [
                'key3' => 'value3',
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ]);

        $result2 = $analyzer->analyze($product2, Context::createDefaultContext(), $config);
        $words2 = $result2->map(fn (AnalyzedKeyword $keyword) => $keyword->getKeyword());
        sort($words2);

        sort($words1);

        // Both results should be identical
        static::assertSame($words1, $words2);
        static::assertEquals(
            ['value1', 'value1 value2 value3', 'value2', 'value3'],
            $words1,
        );
    }

    private static function getLongTextDescription(): string
    {
        return self::getLongTextPart1() . self::getLongTextPart2();
    }

    private static function getLongTextPart1(): string
    {
        return 'This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long descripti';
    }

    private static function getLongTextPart2(): string
    {
        return 'on. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description. This is a long description.';
    }
}

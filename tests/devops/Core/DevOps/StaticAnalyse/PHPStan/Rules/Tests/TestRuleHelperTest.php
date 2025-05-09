<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestReflectionClassInterface;
use Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Tests\TestRuleHelper;

/**
 * @internal
 */
#[CoversClass(TestRuleHelper::class)]
class TestRuleHelperTest extends TestCase
{
    #[DataProvider('classProvider')]
    public function testIsTestClass(string $className, bool $extendsTestCase, bool $isTestClass, bool $isUnitTestClass): void
    {
        $classReflection = $this->createMock(TestReflectionClassInterface::class);
        $classReflection
            ->method('getName')
            ->willReturn($className);

        if ($extendsTestCase) {
            $parentClass = $this->createMock(TestReflectionClassInterface::class);
            $parentClass
                ->method('getName')
                ->willReturn(TestCase::class);

            $classReflection
                ->method('getParents')
                ->willReturn([$parentClass]);
        }

        static::assertSame($isTestClass, TestRuleHelper::isTestClass($classReflection));
        static::assertSame($isUnitTestClass, TestRuleHelper::isUnitTestClass($classReflection));
    }

    public static function classProvider(): \Generator
    {
        yield [
            'className' => 'Shopware\Some\NonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Commercial\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Tests\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Tests\Unit\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => true,
        ];

        yield [
            'className' => 'Shopware\Tests\Integration\SomeTestClass',
            'extendsTestCase' => true,
            'isTestClass' => true,
            'isUnitTestClass' => false,
        ];

        yield [
            'className' => 'Shopware\Tests\SomeNonTestClass',
            'extendsTestCase' => false,
            'isTestClass' => false,
            'isUnitTestClass' => false,
        ];
    }
}

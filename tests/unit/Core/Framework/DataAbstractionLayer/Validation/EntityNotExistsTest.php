<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Validation\EntityNotExists;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(EntityNotExists::class)]
class EntityNotExistsTest extends TestCase
{
    public function testConstructor(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            criteria: $criteria,
            primaryProperty: 'customerId',
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame($criteria, $entityNotExists->getCriteria());
        static::assertSame('customerId', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutCriteria(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
        $context = Context::createDefaultContext();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            primaryProperty: 'customerId',
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame('customerId', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutPrimaryProperty(): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
            criteria: $criteria,
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame($criteria, $entityNotExists->getCriteria());
        static::assertSame('id', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutPrimaryPropertyAndCriteria(): void
    {
        $context = Context::createDefaultContext();

        $entityNotExists = new EntityNotExists(
            entity: 'product_review',
            context: $context,
        );

        static::assertSame('product_review', $entityNotExists->getEntity());
        static::assertSame($context, $entityNotExists->getContext());
        static::assertSame('id', $entityNotExists->getPrimaryProperty());
    }

    public function testConstructorWithoutEntity(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        static::expectException(FrameworkException::class);

        new EntityNotExists(
            context: $context,
            criteria: $criteria,
            primaryProperty: 'customerId',
        );
    }

    public function testConstructorWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $criteria = new Criteria();

        static::expectException(FrameworkException::class);

        /** @phpstan-ignore argument.type (for test purpose) */
        new EntityNotExists([
            'entity' => 'product_review',
            'criteria' => $criteria,
            'primaryProperty' => 'customerId',
        ]);
    }

    public function testConstructorWithInvalidCriteria(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);
        $context = Context::createDefaultContext();

        static::expectException(FrameworkException::class);

        /** @phpstan-ignore argument.type (for test purpose) */
        new EntityNotExists([
            'entity' => 'product_review',
            'context' => $context,
            'criteria' => 'invalid',
            'primaryProperty' => 'customerId',
        ]);
    }

    public function testConstructorWithInvalidPrimaryProperty(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $context = Context::createDefaultContext();
        $criteria = new Criteria();

        static::expectException(FrameworkException::class);

        /** @phpstan-ignore argument.type (for test purpose) */
        new EntityNotExists([
            'entity' => 'product_review',
            'context' => $context,
            'criteria' => $criteria,
            'primaryProperty' => 123,
        ]);
    }
}

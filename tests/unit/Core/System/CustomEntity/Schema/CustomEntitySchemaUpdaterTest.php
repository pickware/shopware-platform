<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomEntity\Schema;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomEntity\Schema\CustomEntitySchemaUpdater;
use Shopware\Core\System\CustomEntity\Schema\SchemaUpdater;
use Symfony\Component\Lock\LockFactory;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CustomEntitySchemaUpdater::class)]
class CustomEntitySchemaUpdaterTest extends TestCase
{
    public function testAddsDoctrineTypeMappingForEnum(): void
    {
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->expects($this->once())
            ->method('registerDoctrineTypeMapping')
            ->with('enum', 'string');

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getDatabasePlatform')
            ->willReturn($platform);

        $updater = new CustomEntitySchemaUpdater(
            $connection,
            $this->createMock(LockFactory::class),
            $this->createMock(SchemaUpdater::class),
        );

        $updater->update();
    }
}

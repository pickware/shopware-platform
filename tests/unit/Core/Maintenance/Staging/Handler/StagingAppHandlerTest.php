<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;
use Shopware\Core\Maintenance\Staging\Handler\StagingAppHandler;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * @internal
 */
#[CoversClass(StagingAppHandler::class)]
class StagingAppHandlerTest extends TestCase
{
    public function testDeletion(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->method('fetchAllAssociative')
            ->willReturn([
                ['id' => 'app_id', 'integration_id' => 'integration_id', 'name' => 'test'],
            ]);

        $tables = [];
        $ids = [];

        $connection
            ->method('delete')
            ->willReturnCallback(function (string $table, array $criteria) use (&$tables, &$ids): int {
                $tables[] = $table;
                $ids[] = $criteria['id'];

                return 1;
            });

        $shopIdProvider = $this->createMock(ShopIdProvider::class);
        $shopIdProvider->expects($this->once())
            ->method('deleteShopId');

        $handler = new StagingAppHandler($connection, $shopIdProvider);
        $handler->__invoke(new SetupStagingEvent(
            Context::createDefaultContext(),
            $this->createMock(SymfonyStyle::class),
            false,
            []
        ));

        static::assertSame(['app', 'integration'], $tables);
        static::assertSame(['app_id', 'integration_id'], $ids);
    }
}

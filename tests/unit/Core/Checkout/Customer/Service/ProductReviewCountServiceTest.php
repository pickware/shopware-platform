<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Customer\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Service\ProductReviewCountService;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewCountService::class)]
class ProductReviewCountServiceTest extends TestCase
{
    private ProductReviewCountService $productReviewCountService;

    private MockObject&Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->productReviewCountService = new ProductReviewCountService($this->connection);
    }

    public function testUpdateReviewCountWithInvalidReviewIds(): void
    {
        $this->connection->expects($this->once())->method('fetchFirstColumn')->willReturn([]);
        $this->connection->expects($this->never())->method('executeStatement');

        $this->productReviewCountService->updateReviewCount([]);
    }

    public function testUpdateReviewCount(): void
    {
        $this->connection->expects($this->once())->method('fetchFirstColumn')->willReturn(['foobar', 'barfoo']);
        $this->connection->expects($this->exactly(2))->method('executeStatement');

        $this->productReviewCountService->updateReviewCount([]);
    }
}

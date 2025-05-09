<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Gateway\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Gateway\Template\ActiveDateRange;
use Shopware\Core\Checkout\Promotion\Gateway\Template\PermittedAutomaticPromotions;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

/**
 * @internal
 */
#[CoversClass(PermittedAutomaticPromotions::class)]
#[Package('checkout')]
class PermittedAutomaticPromotionsTest extends TestCase
{
    private SalesChannelEntity $salesChannel;

    protected function setUp(): void
    {
        $this->salesChannel = new SalesChannelEntity();
        $this->salesChannel->setId('DE');
    }

    /**
     * This test verifies, that we get the
     * expected and defined criteria from the template.
     */
    #[Group('promotions')]
    public function testCriteria(): void
    {
        $template = new PermittedAutomaticPromotions($this->salesChannel->getId());

        static::assertEquals($this->getExpectedFilter()->getQueries(), $template->getQueries());
    }

    private function getExpectedFilter(): MultiFilter
    {
        return new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('active', true),
                new EqualsFilter('promotion.salesChannels.salesChannelId', $this->salesChannel->getId()),
                // yes, i know, this is not the best isolation, but its actually what we want
                new ActiveDateRange(),
                new EqualsFilter('useCodes', false),
            ]
        );
    }
}

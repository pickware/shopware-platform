<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Sitemap;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
class SitemapPageLoadedEvent extends PageLoadedEvent
{
    /**
     * @var SitemapPage
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $page;

    public function __construct(
        SitemapPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        $this->page = $page;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): SitemapPage
    {
        return $this->page;
    }
}

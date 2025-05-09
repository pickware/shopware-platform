<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadCollection;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class DownloadRoute extends AbstractDownloadRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<OrderLineItemDownloadCollection> $downloadRepository
     */
    public function __construct(
        private readonly EntityRepository $downloadRepository,
        private readonly DownloadResponseGenerator $downloadResponseGenerator
    ) {
    }

    public function getDecorated(): AbstractDownloadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/order/download/{orderId}/{downloadId}', name: 'store-api.account.order.single.download', methods: ['GET'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true])]
    public function load(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        $downloadId = $request->get('downloadId', false);
        $orderId = $request->get('orderId', false);

        if (!$customer) {
            throw CustomerException::customerNotLoggedIn();
        }

        if ($downloadId === false || $orderId === false) {
            // @deprecated tag:v6.8.0 - remove this if block
            if (!Feature::isActive('v6.8.0.0')) {
                // @phpstan-ignore-next-line
                throw RoutingException::missingRequestParameter(!$downloadId ? 'downloadId' : 'orderId');
            }
            throw CustomerException::missingRequestParameter(!$downloadId ? 'downloadId' : 'orderId');
        }

        $criteria = (new Criteria([$downloadId]))
            ->addAssociation('media')
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('orderLineItem.order.id', $orderId),
                new EqualsFilter('orderLineItem.order.orderCustomer.customerId', $customer->getId()),
                new EqualsFilter('accessGranted', true),
            ]));

        $download = $this->downloadRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$download || !$download->getMedia()) {
            throw CustomerException::downloadFileNotFound($downloadId);
        }

        $media = $download->getMedia();

        return $this->downloadResponseGenerator->getResponse($media, $context);
    }
}

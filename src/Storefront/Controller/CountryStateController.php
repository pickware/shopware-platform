<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Routing\StorefrontRouteScope;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoadedHook;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StorefrontRouteScope::ID]])]
#[Package('fundamentals@discovery')]
class CountryStateController extends StorefrontController
{
    /**
     * @internal
     */
    public function __construct(private readonly CountryStateDataPageletLoader $countryStateDataPageletLoader)
    {
    }

    /**
     * @deprecated tag:v6.8.0 - reason:remove-route - Remove POST request and use GET instead only
     */
    #[Route(path: '/country/country-state-data', name: 'frontend.country.country.data', defaults: ['XmlHttpRequest' => true, '_httpCache' => true], methods: ['GET', 'POST'])]
    public function getCountryData(Request $request, SalesChannelContext $context): Response
    {
        $countryId = (string) $request->get('countryId');

        if (!$countryId) {
            throw RoutingException::missingRequestParameter('countryId');
        }

        $countryStateDataPagelet = $this->countryStateDataPageletLoader->load($countryId, $request, $context);

        $this->hook(new CountryStateDataPageletLoadedHook($countryStateDataPagelet, $context));

        return new JsonResponse([
            'states' => $countryStateDataPagelet->getStates(),
        ]);
    }
}

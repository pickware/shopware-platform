<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Hmac;

use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Hmac\Guzzle\AuthMiddleware;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\LocaleProvider;
use Shopware\Core\Framework\Store\InAppPurchase;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class QuerySigner
{
    public function __construct(
        private readonly string $shopUrl,
        private readonly string $shopwareVersion,
        private readonly LocaleProvider $localeProvider,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly InAppPurchase $inAppPurchase,
    ) {
    }

    public function signUri(string $uri, AppEntity $app, Context $context): UriInterface
    {
        $secret = $app->getAppSecret();
        if ($secret === null) {
            throw AppException::appSecretMissing($app->getName());
        }

        $unsignedUri = Uri::withQueryValues(new Uri($uri), [
            'shop-id' => $this->shopIdProvider->getShopId(),
            'shop-url' => $this->shopUrl,
            'timestamp' => (string) (new \DateTime())->getTimestamp(),
            'sw-version' => $this->shopwareVersion,
            'app-version' => $app->getVersion(),
            'in-app-purchases' => \urlencode($this->inAppPurchase->getJWTByExtension($app->getName()) ?? ''),
            AuthMiddleware::SHOPWARE_CONTEXT_LANGUAGE => $context->getLanguageId(),
            AuthMiddleware::SHOPWARE_USER_LANGUAGE => $this->localeProvider->getLocaleFromContext($context),
        ]);

        return Uri::withQueryValue(
            $unsignedUri,
            'shopware-shop-signature',
            (new RequestSigner())->signPayload($unsignedUri->getQuery(), $secret)
        );
    }
}

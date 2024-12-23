<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\InAppPurchases\Gateway;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\InAppPurchases\Event\InAppPurchasesGatewayEvent;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayload;
use Shopware\Core\Framework\App\InAppPurchases\Payload\InAppPurchasesPayloadService;
use Shopware\Core\Framework\App\InAppPurchases\Response\InAppPurchasesResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('checkout')]
class InAppPurchasesGateway
{
    public function __construct(
        readonly private InAppPurchasesPayloadService $payloadService,
        readonly private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function process(InAppPurchasesPayload $payload, Context $context, AppEntity $app): ?InAppPurchasesResponse
    {
        $url = $app->getInAppPurchasesGatewayUrl();

        if ($url === null) {
            return null;
        }

        $response = $this->payloadService->request($url, $payload, $app, $context);

        $this->eventDispatcher->dispatch(new InAppPurchasesGatewayEvent($response));

        return $response;
    }
}

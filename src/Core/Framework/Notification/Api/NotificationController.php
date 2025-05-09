<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Notification\Api;

use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 */
#[Route(defaults: ['_routeScope' => ['api']])]
#[Package('framework')]
class NotificationController extends AbstractController
{
    final public const NOTIFICATION = 'notification';

    final public const LIMIT = 5;

    /**
     * @internal
     */
    public function __construct(
        private readonly RateLimiter $rateLimiter,
        private readonly NotificationService $notificationService
    ) {
    }

    #[Route(path: '/api/notification', name: 'api.notification', defaults: ['_acl' => ['notification:create']], methods: ['POST'])]
    public function saveNotification(Request $request, Context $context): Response
    {
        $status = (string) $request->request->get('status');
        $message = (string) $request->request->get('message');
        $adminOnly = (bool) $request->request->get('adminOnly', false);

        try {
            $requiredPrivileges = $request->request->all('requiredPrivileges');
        } catch (BadRequestException) {
            throw RoutingException::invalidRequestParameter('requiredPrivileges');
        }

        $source = $context->getSource();
        if (!$source instanceof AdminApiSource) {
            throw ApiException::invalidAdminSource($context->getSource()::class);
        }

        if (empty($status)) {
            throw RoutingException::missingRequestParameter('status');
        }

        if (empty($message)) {
            throw RoutingException::missingRequestParameter('message');
        }

        $integrationId = $source->getIntegrationId();
        $createdByUserId = $source->getUserId();

        try {
            $cacheKey = $createdByUserId ?? $integrationId . '-' . $request->getClientIp();
            $this->rateLimiter->ensureAccepted(self::NOTIFICATION, $cacheKey);
        } catch (RateLimitExceededException $exception) {
            throw ApiException::notificationThrottled($exception->getWaitTime(), $exception);
        }

        $notificationId = Uuid::randomHex();
        $this->notificationService->createNotification([
            'id' => $notificationId,
            'status' => $status,
            'message' => $message,
            'adminOnly' => $adminOnly,
            'requiredPrivileges' => $requiredPrivileges,
            'createdByIntegrationId' => $integrationId,
            'createdByUserId' => $createdByUserId,
        ], $context);

        return new JsonResponse(['id' => $notificationId]);
    }

    #[Route(path: '/api/notification/message', name: 'api.notification.message', methods: ['GET'])]
    public function fetchNotification(Request $request, Context $context): Response
    {
        $limit = $request->query->get('limit');
        $limit = $limit ? (int) $limit : self::LIMIT;
        $latestTimestamp = $request->query->has('latestTimestamp') ? (string) $request->query->get('latestTimestamp') : null;

        $responseData = $this->notificationService->getNotifications($context, $limit, $latestTimestamp);

        return new JsonResponse($responseData);
    }
}

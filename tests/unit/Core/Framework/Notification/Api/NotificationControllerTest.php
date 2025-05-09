<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Notification\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\Exception\InvalidContextSourceException;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Notification\Api\NotificationController;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\RateLimiter\Exception\RateLimitExceededException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\RoutingException;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotificationController::class)]
class NotificationControllerTest extends TestCase
{
    private Context $context;

    private MockObject&NotificationService $notificationService;

    private MockObject&RateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiter::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->context = Context::createDefaultContext(new AdminApiSource('123', '345'));
    }

    public function testSaveNotificationThrowsRoutingExceptionWhenBadRequest(): void
    {
        $this->expectExceptionObject(RoutingException::invalidRequestParameter('requiredPrivileges'));

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $controller->saveNotification(new Request([], ['requiredPrivileges' => true]), $this->context);
    }

    public function testSaveNotificationThrowsInvalidContextSourceExceptionWhenWrongSource(): void
    {
        $this->expectExceptionObject(
            new InvalidContextSourceException(AdminApiSource::class, SystemSource::class)
        );

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $controller->saveNotification(new Request(), Context::createDefaultContext());
    }

    public function testSaveNotificationThrowsRoutingExceptionWhenMissingRequestStatus(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('status'));

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $controller->saveNotification(new Request(), $this->context);
    }

    public function testSaveNotificationThrowsRoutingExceptionWhenMissingRequestMessage(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('message'));

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $controller->saveNotification(new Request([], ['status' => 'ok']), $this->context);
    }

    public function testSaveNotificationThrowsApiExceptionWhenLimitIsReachedAndUserIdExists(): void
    {
        $exception = new RateLimitExceededException(42);
        $this->expectExceptionObject(ApiException::notificationThrottled($exception->getWaitTime(), $exception));

        $this->rateLimiter->expects($this->once())->method('ensureAccepted')
            ->with('notification', '123')
            ->willThrowException($exception);

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $controller->saveNotification(new Request([], ['status' => 'ok', 'message' => 'ok']), $this->context);
    }

    public function testSaveNotificationThrowsApiExceptionWhenLimitIsReachedAndUserIdIsNull(): void
    {
        $this->context = Context::createDefaultContext(new AdminApiSource(null, '345'));
        $exception = new RateLimitExceededException(12);
        $this->expectExceptionObject(ApiException::notificationThrottled($exception->getWaitTime(), $exception));

        $this->rateLimiter->expects($this->once())->method('ensureAccepted')
            ->with('notification', '345-')
            ->willThrowException($exception);

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $controller->saveNotification(new Request([], ['status' => 'ok', 'message' => 'ok']), $this->context);
    }

    public function testSaveNotificationInvokesNotificationService(): void
    {
        $this->notificationService->expects($this->once())->method('createNotification');

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $response = $controller->saveNotification(new Request([], ['status' => 'ok', 'message' => 'ok']), $this->context);

        static::assertNotFalse($response->getContent());
        $result = \json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertNotFalse($result);
        static::assertArrayHasKey('id', $result);
        static::assertIsString($result['id']);
    }

    public function testFetchNotificationWhenRequestQueryHasNoLimitSet(): void
    {
        $this->notificationService->expects($this->once())->method('getNotifications')
            ->with($this->context, NotificationController::LIMIT, null);

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $response = $controller->fetchNotification(new Request([], []), $this->context);

        static::assertNotFalse($response->getContent());
    }

    public function testFetchNotificationWhenRequestQuerytHasLimitSet(): void
    {
        $this->notificationService->expects($this->once())->method('getNotifications')
            ->with($this->context, 100, null);

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $response = $controller->fetchNotification(new Request(['limit' => '100'], []), $this->context);

        static::assertNotFalse($response->getContent());
    }

    public function testFetchNotificationWhenRequestQueryHasLatestTimestamp(): void
    {
        $this->notificationService->expects($this->once())->method('getNotifications')
            ->with($this->context, NotificationController::LIMIT, '1719097358');

        $controller = new NotificationController($this->rateLimiter, $this->notificationService);
        $response = $controller->fetchNotification(new Request(['latestTimestamp' => 1719097358], []), $this->context);

        static::assertNotFalse($response->getContent());
    }
}

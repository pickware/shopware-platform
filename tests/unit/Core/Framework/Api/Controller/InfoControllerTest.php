<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Content\Flow\Api\FlowActionCollector;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\Controller\InfoController;
use Shopware\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Increment\IncrementGatewayRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Store\InAppPurchase;
use Shopware\Core\Framework\Test\Store\StaticInAppPurchaseFactory;
use Shopware\Core\Maintenance\System\Service\AppUrlVerifier;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Asset\UrlPackage;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(InfoController::class)]
class InfoControllerTest extends TestCase
{
    private InfoController $infoController;

    private ParameterBagInterface&MockObject $parameterBagMock;

    private RouterInterface&MockObject $routerMock;

    private InAppPurchase $inAppPurchase;

    private ShopIdProvider&MockObject $shopIdProvider;

    public function testConfig(): void
    {
        $this->createInstance();

        $this->shopIdProvider->expects($this->once())->method('getShopId')->willReturn('shop-id');

        $response = $this->infoController->config(Context::createDefaultContext(), Request::create('http://localhost'));
        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true);
        static::assertIsArray($data);
        static::assertArrayHasKey('version', $data);
        static::assertSame('6.6.9999999-dev', $data['version']);
        static::assertArrayHasKey('versionRevision', $data);
        static::assertSame('PHPUnit', $data['versionRevision']);
        static::assertArrayHasKey('adminWorker', $data);
        static::assertArrayHasKey('shopId', $data);
        static::assertSame('shop-id', $data['shopId']);

        $workerConfig = $data['adminWorker'];
        static::assertArrayHasKey('enableAdminWorker', $workerConfig);
        static::assertTrue($workerConfig['enableAdminWorker']);
        static::assertArrayHasKey('enableQueueStatsWorker', $workerConfig);
        static::assertTrue($workerConfig['enableQueueStatsWorker']);
        static::assertArrayHasKey('enableNotificationWorker', $workerConfig);
        static::assertTrue($workerConfig['enableNotificationWorker']);
        static::assertArrayHasKey('transports', $workerConfig);
        static::assertIsArray($workerConfig['transports']);
        static::assertCount(1, $workerConfig['transports']);
        static::assertSame('slow', $workerConfig['transports'][0]);

        static::assertArrayHasKey('bundles', $data);
        $bundles = $data['bundles'];
        static::assertIsArray($bundles);
        static::assertCount(1, $bundles);
        static::assertArrayHasKey('AdminExtensionApiPluginWithLocalEntryPoint', $bundles);
        $bundle = $bundles['AdminExtensionApiPluginWithLocalEntryPoint'];
        static::assertIsArray($bundle);
        static::assertArrayHasKey('css', $bundle);
        static::assertIsArray($bundle['css']);
        static::assertCount(0, $bundle['css']);
        static::assertArrayHasKey('js', $bundle);
        static::assertIsArray($bundle['js']);
        static::assertCount(0, $bundle['js']);
        static::assertArrayHasKey('baseUrl', $bundle);
        static::assertSame('/admin/adminextensionapipluginwithlocalentrypoint/index.html', $bundle['baseUrl']);
        static::assertArrayHasKey('type', $bundle);
        static::assertSame('plugin', $bundle['type']);

        static::assertArrayHasKey('settings', $data);
        $settings = $data['settings'];
        static::assertIsArray($settings);
        static::assertArrayHasKey('enableUrlFeature', $settings);
        static::assertTrue($settings['enableUrlFeature']);
        static::assertArrayHasKey('appUrlReachable', $settings);
        static::assertFalse($settings['appUrlReachable']);
        static::assertArrayHasKey('appsRequireAppUrl', $settings);
        static::assertFalse($settings['appsRequireAppUrl']);
        static::assertArrayHasKey('private_allowed_extensions', $settings);
        static::assertFalse($settings['private_allowed_extensions']);
        static::assertArrayHasKey('enableHtmlSanitizer', $settings);
        static::assertTrue($settings['enableHtmlSanitizer']);

        static::assertArrayHasKey('inAppPurchases', $data);
        $inAppPurchases = $data['inAppPurchases'];
        static::assertIsArray($inAppPurchases);
        static::assertCount(1, $inAppPurchases);
        static::assertArrayHasKey('SwagApp', $inAppPurchases);
        static::assertSame(['SwagApp_premium'], $inAppPurchases['SwagApp']);
    }

    public function testReturnsCurrentShopIdIfAppUrlChangeIsDetected(): void
    {
        $this->createInstance();

        $this->shopIdProvider
            ->expects($this->once())
            ->method('getShopId')
            ->willThrowException(new AppUrlChangeDetectedException('http://localhost', 'http://globalhost', 'current-shop-id'));

        $response = $this->infoController->config(Context::createDefaultContext(), Request::create('http://localhost'));

        $content = $response->getContent();
        static::assertIsString($content);

        $data = json_decode($content, true);
        static::assertArrayHasKey('shopId', $data);
        static::assertSame('current-shop-id', $data['shopId']);
    }

    private function createInstance(): void
    {
        $kernel = new StubKernel([
            new AdminExtensionApiPluginWithLocalEntryPoint(true, __DIR__ . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint'),
        ]);

        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);
        $this->routerMock = $this->createMock(RouterInterface::class);
        $this->inAppPurchase = StaticInAppPurchaseFactory::createWithFeatures(['SwagApp' => ['SwagApp_premium']]);
        $this->shopIdProvider = $this->createMock(ShopIdProvider::class);

        $this->parameterBagMock->method('get')
            ->willReturnMap([
                ['shopware.html_sanitizer.enabled', true],
                ['shopware.filesystem.private_allowed_extensions', false],
                ['shopware.admin_worker.transports', ['slow']],
                ['shopware.admin_worker.enable_notification_worker', true],
                ['shopware.admin_worker.enable_queue_stats_worker', true],
                ['shopware.admin_worker.enable_admin_worker', true],
                ['kernel.shopware_version', '6.6.9999999-dev'],
                ['kernel.shopware_version_revision', 'PHPUnit'],
                ['shopware.media.enable_url_upload_feature', true],
            ]);

        $this->routerMock->method('generate')
            ->with(
                'administration.plugin.index',
                [
                    'pluginName' => 'adminextensionapipluginwithlocalentrypoint',
                ]
            )
            ->willReturn('/admin/adminextensionapipluginwithlocalentrypoint/index.html');

        $this->infoController = new InfoController(
            $this->createMock(DefinitionService::class),
            $this->parameterBagMock,
            $kernel,
            $this->createMock(BusinessEventCollector::class),
            $this->createMock(IncrementGatewayRegistry::class),
            $this->createMock(Connection::class),
            $this->createMock(AppUrlVerifier::class),
            $this->routerMock,
            $this->createMock(FlowActionCollector::class),
            new StaticSystemConfigService(),
            $this->createMock(ApiRouteInfoResolver::class),
            $this->inAppPurchase,
            new ViteFileAccessorDecorator(
                [],
                $this->createMock(UrlPackage::class),
                $kernel,
                new Filesystem(),
            ),
            new Filesystem(),
            $this->shopIdProvider,
        );
    }
}

/**
 * @internal
 */
class AdminExtensionApiPluginWithLocalEntryPoint extends Plugin
{
    public function getPath(): string
    {
        return __DIR__ . '/Fixtures/AdminExtensionApiPluginWithLocalEntryPoint';
    }
}

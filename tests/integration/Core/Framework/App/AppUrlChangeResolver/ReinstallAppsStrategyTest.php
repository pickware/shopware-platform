<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\AppUrlChangeResolver;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppUrlChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\Lifecycle\Registration\AppRegistrationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\AppSystemTestBehaviour;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ReinstallAppsStrategyTest extends TestCase
{
    use AppSystemTestBehaviour;
    use EnvTestBehaviour;
    use IntegrationTestBehaviour;

    private ShopIdProvider $shopIdProvider;

    private Context $context;

    protected function setUp(): void
    {
        $this->shopIdProvider = static::getContainer()->get(ShopIdProvider::class);
        $this->context = Context::createDefaultContext();
    }

    public function testGetName(): void
    {
        $reinstallAppsResolver = static::getContainer()->get(ReinstallAppsStrategy::class);

        static::assertSame(
            ReinstallAppsStrategy::STRATEGY_NAME,
            $reinstallAppsResolver->getName()
        );
        static::assertIsString($reinstallAppsResolver->getDescription());
    }

    public function testItReRegistersInstalledApps(): void
    {
        $appDir = (string) realpath(__DIR__ . '/../Manifest/_fixtures/test');
        $this->loadAppsFromDir($appDir);

        $app = $this->getInstalledApp($this->context);

        $shopId = $this->changeAppUrl();

        $registrationsService = $this->createMock(AppRegistrationService::class);
        $registrationsService->expects($this->once())
            ->method('registerApp')
            ->with(
                static::callback(static fn (Manifest $manifest): bool => $manifest->getPath() === $appDir),
                $app->getId(),
                static::isString(),
                static::isInstanceOf(Context::class)
            );

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(AppInstalledEvent::class));

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            new StaticSourceResolver(['test' => new Filesystem($appDir)]),
            static::getContainer()->get('app.repository'),
            $registrationsService,
            $this->shopIdProvider,
            $eventDispatcher
        );

        $reinstallAppsResolver->resolve($this->context);

        static::assertNotEquals($shopId, $this->shopIdProvider->getShopId());

        // assert secret access key changed
        $updatedApp = $this->getInstalledApp($this->context);
        static::assertNotNull($app->getIntegration());
        static::assertNotNull($updatedApp->getIntegration());

        static::assertNotEquals(
            $app->getIntegration()->getSecretAccessKey(),
            $updatedApp->getIntegration()->getSecretAccessKey()
        );
    }

    public function testItIgnoresAppsWithoutSetup(): void
    {
        $appDir = __DIR__ . '/../Lifecycle/Registration/_fixtures/no-setup';
        $this->loadAppsFromDir($appDir);

        $shopId = $this->changeAppUrl();

        $registrationsService = $this->createMock(AppRegistrationService::class);
        $registrationsService->expects($this->never())
            ->method('registerApp');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->never())
            ->method('dispatch');

        $reinstallAppsResolver = new ReinstallAppsStrategy(
            new StaticSourceResolver(['no-setup' => new Filesystem($appDir)]),
            static::getContainer()->get('app.repository'),
            $registrationsService,
            $this->shopIdProvider,
            $eventDispatcher
        );

        $reinstallAppsResolver->resolve($this->context);

        static::assertNotEquals($shopId, $this->shopIdProvider->getShopId());
    }

    private function changeAppUrl(): string
    {
        $shopId = $this->shopIdProvider->getShopId();

        // create AppUrlChange
        $this->setEnvVars(['APP_URL' => 'https://test.new']);

        try {
            $this->shopIdProvider->getShopId();
            static::fail('Expected exception AppUrlChangeDetectedException was not thrown');
        } catch (AppUrlChangeDetectedException) {
            // exception is expected
        }

        return $shopId;
    }

    private function getInstalledApp(Context $context): AppEntity
    {
        /** @var EntityRepository<AppCollection> $appRepo */
        $appRepo = static::getContainer()->get('app.repository');

        $criteria = new Criteria();
        $criteria->addAssociation('integration');
        $app = $appRepo->search($criteria, $context)->getEntities()->first();
        static::assertNotNull($app);

        return $app;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\Subscriber;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\Lifecycle\AbstractAppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class AppLifecycleSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private AbstractAppLifecycle $appLifecycle;

    /**
     * @var EntityRepository<AppCollection>
     */
    private EntityRepository $appRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->appRepository = static::getContainer()->get('app.repository');

        $userId = static::getContainer()->get('user.repository')->searchIds(new Criteria(), Context::createDefaultContext())->firstId();
        $source = new AdminApiSource($userId);
        $source->setIsAdmin(true);

        $this->appLifecycle = static::getContainer()->get(AppLifecycle::class);
        $this->context = new Context(new SystemSource(), [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM]);
    }

    #[DataProvider('themeProvideData')]
    public function testThemeRemovalOnDelete(bool $keepUserData): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../fixtures/Apps/theme/manifest.xml');
        $this->appLifecycle->install($manifest, new AppInstallParameters(), $this->context);

        $apps = $this->appRepository->search(new Criteria(), $this->context)->getEntities();
        static::assertCount(1, $apps);
        static::assertNotNull($apps->first());
        $app = [
            'id' => $apps->first()->getId(),
            'name' => $apps->first()->getName(),
            'roleId' => $apps->first()->getAclRoleId(),
        ];

        $themeRepo = static::getContainer()->get('theme.repository');

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', $app['name']));

        static::assertCount(1, $themeRepo->search($criteria, $this->context)->getElements());

        $this->appLifecycle->delete($app['name'], $app, $this->context, $keepUserData);
        static::assertCount($keepUserData ? 1 : 0, $themeRepo->search($criteria, $this->context)->getElements());

        $apps = $this->appRepository->searchIds(new Criteria(), $this->context)->getIds();
        static::assertCount(0, $apps);
    }

    /**
     * @return array<string, array<int, bool>>
     */
    public static function themeProvideData(): array
    {
        return [
            'Test with keep data' => [true],
            'Test without keep data' => [false],
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Category\Event;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\NavigationLoadedEvent;
use Shopware\Core\Content\Category\Service\NavigationLoader;
use Shopware\Core\Content\Category\Service\NavigationLoaderInterface;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
class NavigationLoadedEventTest extends TestCase
{
    use IntegrationTestBehaviour;

    protected NavigationLoaderInterface $loader;

    protected function setUp(): void
    {
        $this->loader = static::getContainer()->get(NavigationLoader::class);
        parent::setUp();
    }

    public function testEventDispatched(): void
    {
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects($this->once())->method('__invoke');

        $dispatcher = static::getContainer()->get('event_dispatcher');
        $this->addEventListener($dispatcher, NavigationLoadedEvent::class, $listener);

        $context = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $navigationId = $context->getSalesChannel()->getNavigationCategoryId();

        $this->loader->load($navigationId, $context, $navigationId);
    }
}

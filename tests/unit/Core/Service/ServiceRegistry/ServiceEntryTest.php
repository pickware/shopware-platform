<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service\ServiceRegistry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Service\ServiceRegistry\ServiceEntry;

/**
 * @internal
 */
#[CoversClass(ServiceEntry::class)]
class ServiceEntryTest extends TestCase
{
    public function testServiceRegistryEntry(): void
    {
        $entry = new ServiceEntry('MyCoolService', 'My Cool Service', 'https://some-service.com', '/service/lifecycle/choose-app');

        static::assertSame('MyCoolService', $entry->name);
        static::assertSame('https://some-service.com', $entry->host);
        static::assertSame('My Cool Service', $entry->description);
        static::assertSame('/service/lifecycle/choose-app', $entry->appEndpoint);
    }

    public function testServiceRegistryEntryDefaultsToActivateOnInstall(): void
    {
        $entry = new ServiceEntry('MyCoolService', 'My Cool Service', 'https://some-service.com', '/service/lifecycle/choose-app');

        static::assertTrue($entry->activateOnInstall);
    }

    public function testCanConfigureToNotActivateOnInstall(): void
    {
        $entry = new ServiceEntry('MyCoolService', 'My Cool Service', 'https://some-service.com', '/service/lifecycle/choose-app', false);

        static::assertFalse($entry->activateOnInstall);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\HandlerStack;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClientFactory;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SystemConfig\SystemConfigService;

/**
 * @internal
 */
#[Package('checkout')]
class StoreClientFactoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const TEST_STORE_URI = 'http://test-store';

    private const STORE_URI_CONFIG_KEY = 'core.store.apiUri';

    private ?string $originalStoreUri = null;

    #[Before]
    public function updateStoreUri(): void
    {
        $this->originalStoreUri = $this->getApiUrlFromSystemConfig();
        $this->getSystemConfigService()->set(self::STORE_URI_CONFIG_KEY, self::TEST_STORE_URI);
    }

    #[After]
    public function restoreStoreUri(): void
    {
        $this->getSystemConfigService()->set(self::STORE_URI_CONFIG_KEY, $this->originalStoreUri);
    }

    public function testItCreatesAnClientWithBaseConfig(): void
    {
        $storeClientFactory = new StoreClientFactory($this->getSystemConfigService());

        $client = $storeClientFactory->create();
        $config = $this->getConfigFromClient($client);

        static::assertSame(self::TEST_STORE_URI, (string) $config['base_uri']);

        static::assertArrayHasKey('Content-Type', $config['headers']);
        static::assertSame('application/json', $config['headers']['Content-Type']);

        static::assertArrayHasKey('Accept', $config['headers']);
        static::assertSame('application/vnd.api+json,application/json', $config['headers']['Accept']);

        $stack = $config['handler'];
        static::assertInstanceOf(HandlerStack::class, $stack);

        static::assertTrue($stack->hasHandler());
    }

    private function getSystemConfigService(): SystemConfigService
    {
        return static::getContainer()->get(SystemConfigService::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function getConfigFromClient(ClientInterface $client): array
    {
        $reflObject = new \ReflectionObject($client);

        return $reflObject->getProperty('config')->getValue($client);
    }

    private function getApiUrlFromSystemConfig(): string
    {
        $apiUrl = $this->getSystemConfigService()->get(self::STORE_URI_CONFIG_KEY);

        static::assertIsString($apiUrl);

        return $apiUrl;
    }
}

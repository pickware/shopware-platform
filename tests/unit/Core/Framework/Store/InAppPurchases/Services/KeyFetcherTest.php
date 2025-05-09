<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\InAppPurchases\Services;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Authentication\StoreRequestOptionsProvider;
use Shopware\Core\Framework\Store\InAppPurchase\Services\KeyFetcher;
use Shopware\Core\Framework\Store\StoreException;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(KeyFetcher::class)]
class KeyFetcherTest extends TestCase
{
    public function testGetKey(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('get')
            ->with(KeyFetcher::CORE_STORE_JWKS)
            ->willReturn($this->getKey());

        $systemConfig->expects($this->never())
            ->method('set');

        $keyFetcher = new KeyFetcher(
            $this->createMock(ClientInterface::class),
            $this->createMock(StoreRequestOptionsProvider::class),
            $systemConfig,
            $this->createMock(LoggerInterface::class)
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext());

        static::assertSame('sample-key-id', $key->getElements()[0]->kid);
    }

    public function testGetKeyWithForceRefresh(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('get')
            ->with(KeyFetcher::CORE_STORE_JWKS)
            ->willReturn($this->getKey());

        $systemConfig->expects($this->once())
            ->method('set')
            ->with(KeyFetcher::CORE_STORE_JWKS, $this->getKey());

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], $this->getKey()));

        $keyFetcher = new KeyFetcher(
            $client,
            $this->createMock(StoreRequestOptionsProvider::class),
            $systemConfig,
            $this->createMock(LoggerInterface::class)
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext(), true);

        static::assertSame('sample-key-id', $key->getElements()[0]->kid);
    }

    public function testGetKeyReturns400ResponseWithExistingKey(): void
    {
        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('get')
            ->with(KeyFetcher::CORE_STORE_JWKS)
            ->willReturn($this->getKey());

        $systemConfig->expects($this->never())
            ->method('set');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->willReturn(new Response(400));

        $keyFetcher = new KeyFetcher(
            $client,
            $this->createMock(StoreRequestOptionsProvider::class),
            $systemConfig,
            $this->createMock(LoggerInterface::class)
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext(), true);

        static::assertSame('sample-key-id', $key->getElements()[0]->kid);
    }

    /**
     * @deprecated tag:v6.8.0 - reason: see StoreException::jwksNotFound - to be removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetKeyReturns400ResponseWithoutExistingKeyDeprecated(): void
    {
        static::expectException(AppException::class);
        static::expectExceptionMessage('Unable to retrieve JWKS key');

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('get')
            ->with(KeyFetcher::CORE_STORE_JWKS)
            ->willReturn(null);

        $systemConfig->expects($this->never())
            ->method('set');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->willReturn(new Response(400));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Could not fetch the JWKS from the SBP');

        $keyFetcher = new KeyFetcher(
            $client,
            $this->createMock(StoreRequestOptionsProvider::class),
            $systemConfig,
            $logger
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext(), true);

        static::assertSame('sample-key-id', $key->getElements()[0]->kid);
    }

    public function testGetKeyReturns400ResponseWithoutExistingKey(): void
    {
        static::expectException(StoreException::class);
        static::expectExceptionMessage('Unable to retrieve JWKS key');

        $systemConfig = $this->createMock(SystemConfigService::class);
        $systemConfig->expects($this->once())
            ->method('get')
            ->with(KeyFetcher::CORE_STORE_JWKS)
            ->willReturn(null);

        $systemConfig->expects($this->never())
            ->method('set');

        $client = $this->createMock(ClientInterface::class);
        $client->expects($this->once())
            ->method('request')
            ->willReturn(new Response(400));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Could not fetch the JWKS from the SBP');

        $keyFetcher = new KeyFetcher(
            $client,
            $this->createMock(StoreRequestOptionsProvider::class),
            $systemConfig,
            $logger
        );

        $key = $keyFetcher->getKey(Context::createDefaultContext(), true);

        static::assertSame('sample-key-id', $key->getElements()[0]->kid);
    }

    private function getKey(): string
    {
        return '{"keys": [{"kty": "RSA", "kid": "sample-key-id", "use": "sig", "alg": "RS256", "n": "sample-n", "e": "AQAB"}]}';
    }
}

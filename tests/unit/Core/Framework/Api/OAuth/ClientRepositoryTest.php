<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\Client\ApiClient;
use Shopware\Core\Framework\Api\OAuth\ClientRepository;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ClientRepository::class)]
class ClientRepositoryTest extends TestCase
{
    private ClientRepository $clientRepository;

    private Connection&MockObject $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->createMock(Connection::class);
        $this->clientRepository = new ClientRepository($this->connection);
    }

    public function testValidateClientWithInvalidGrantTypeThrowException(): void
    {
        static::expectExceptionMessage('The authorization grant type is not supported by the authorization server.');
        $this->clientRepository->validateClient('clientIdentifier', 'clientSecret', 'unsupportGrantType');
    }

    #[DataProvider('validateClientDataProvider')]
    public function testValidateClient(string $grantType, string $clientIdentifier, string $clientSecret, bool $expectedResult): void
    {
        $this->connection->method('fetchAssociative')->willReturnCallback(function () use ($clientIdentifier, $clientSecret) {
            if ($clientIdentifier === 'SWUAADMIN' && $clientSecret === 'shopware') {
                return [
                    'id' => '123',
                    'secret_access_key' => password_hash($clientSecret, \PASSWORD_BCRYPT),
                ];
            }

            return false;
        });

        $this->connection
            ->expects(
                ($expectedResult && $grantType === 'client_credentials') ? $this->once() : $this->never()
            )
            ->method('update');

        $result = $this->clientRepository->validateClient($clientIdentifier, $clientSecret, $grantType);
        static::assertSame($expectedResult, $result);
    }

    public function testValidateClientMustNotAuthenticateBecauseInvalidClientSecret(): void
    {
        $this->connection
            ->method('fetchAssociative')
            ->willReturnCallback(
                function () {
                    return [
                        'id' => '123',
                        'active' => true,
                        'secret_access_key' => 'secret_access_key',
                    ];
                }
            );

        $this->connection
            ->expects($this->never())
            ->method('update');

        $result = $this->clientRepository->validateClient(
            'SWIAINTEGRATION',
            'wrong-client-secret',
            'client_credentials'
        );

        static::assertFalse($result);
    }

    #[DataProvider('getClientEntityDataProvider')]
    /**
     * @param non-empty-string $clientIdentifier
     */
    public function testGetClientEntity(string $clientIdentifier, ?ClientEntityInterface $expectedResult): void
    {
        $this->connection->method('fetchAssociative')->willReturnCallback(function () use ($clientIdentifier) {
            if ($clientIdentifier === 'SWUAUSERCORRECT') {
                return [
                    'user_id' => Uuid::randomBytes(),
                    'secret_access_key' => 'secret_access_key',
                ];
            }

            if ($clientIdentifier === 'SWIAINTEGRATION') {
                return [
                    'active' => '1',
                    'id' => '123',
                    'secret_access_key' => 'secret_access_key',
                    'label' => 'label',
                ];
            }

            if ($clientIdentifier === 'SWIAINTEGRATIONINACTIVE') {
                return [
                    'active' => '0',
                    'id' => '123',
                    'secret_access_key' => 'secret_access_key',
                    'label' => 'label',
                ];
            }

            return false;
        });

        $this->connection
            ->expects($this->never())
            ->method('update');

        \assert($clientIdentifier !== '');
        $clientEntity = $this->clientRepository->getClientEntity($clientIdentifier);

        if (!$expectedResult instanceof ClientEntityInterface) {
            static::assertNull($clientEntity);

            return;
        }

        static::assertNotNull($clientEntity);
        static::assertInstanceOf(ApiClient::class, $clientEntity);
        static::assertSame($expectedResult->getIdentifier(), $clientEntity->getIdentifier());
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function validateClientDataProvider(): iterable
    {
        yield 'password grant type' => ['password', 'administration', 'shopware', true];
        yield 'refresh_token grant type' => ['refresh_token', 'administration', 'shopware', true];
        yield 'client_credentials grant type with incorrect clientIdentifier' => ['client_credentials', 'SWUAJOHNDOE', 'shopware', false];
        yield 'client_credentials grant type with correct clientIdentifier' => ['client_credentials', 'SWUAADMIN', 'shopware', true];
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function getClientEntityDataProvider(): iterable
    {
        yield 'user origin clientIdentifier' => ['SWUAUSERCORRECT', new ApiClient('SWUAUSERCORRECT', true, 'foo', true)];
        yield 'user origin clientIdentifier invalid' => ['SWUAUSERINVALID', null];
        yield 'integration origin clientIdentifier' => ['SWIAINTEGRATION', new ApiClient('SWIAINTEGRATION', true, 'foo', true)];
        yield 'integration origin clientIdentifier invalid' => ['SWIAINTEGRATIONINVALID', null];
        yield 'integration origin clientIdentifier inactive' => ['SWIAINTEGRATIONINACTIVE', null];
        yield 'sales channel origin clientIdentifier' => ['SWSCSALESCHANNEL', null];
        yield 'administration clientIdentifier' => ['administration', new ApiClient('administration', true, confidential: false)];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OAuth;

use Doctrine\DBAL\Connection;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use Shopware\Core\Framework\Api\OAuth\User\User;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('framework')]
class UserRepository implements UserRepositoryInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly Connection $connection)
    {
    }

    public function getUserEntityByUserCredentials(
        string $username,
        string $password,
        string $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        $builder = $this->connection->createQueryBuilder();
        $user = $builder->select('user.id', 'user.password')
            ->from('user')
            ->where('username = :username')
            ->setParameter('username', $username)
            ->executeQuery()
            ->fetchAssociative();

        if (!$user) {
            return null;
        }

        if (!password_verify($password, (string) $user['password'])) {
            return null;
        }

        return new User(Uuid::fromBytesToHex($user['id']));
    }
}

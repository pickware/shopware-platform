<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionEntity;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class VersionCommitEntity extends Entity
{
    use EntityIdTrait;

    protected int $autoIncrement;

    protected ?string $message = null;

    protected ?string $userId = null;

    protected ?string $integrationId = null;

    protected VersionCommitDataCollection $data;

    protected bool $isMerge;

    protected ?VersionEntity $version = null;

    public function getAutoIncrement(): int
    {
        return $this->autoIncrement;
    }

    public function setAutoIncrement(int $autoIncrement): void
    {
        $this->autoIncrement = $autoIncrement;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): void
    {
        $this->message = $message;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): void
    {
        $this->userId = $userId;
    }

    public function getVersionId(): ?string
    {
        return $this->versionId;
    }

    public function getData(): VersionCommitDataCollection
    {
        return $this->data;
    }

    public function setData(VersionCommitDataCollection $data): void
    {
        $this->data = $data;
    }

    public function getIsMerge(): bool
    {
        return $this->isMerge;
    }

    public function setIsMerge(bool $isMerge): void
    {
        $this->isMerge = $isMerge;
    }

    public function getVersion(): ?VersionEntity
    {
        return $this->version;
    }

    public function setVersion(VersionEntity $version): void
    {
        $this->version = $version;
    }

    public function getIntegrationId(): ?string
    {
        return $this->integrationId;
    }

    public function setIntegrationId(string $integrationId): void
    {
        $this->integrationId = $integrationId;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class MediaDefaultFolderEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $entity;

    protected ?MediaFolderEntity $folder = null;

    public function getEntity(): string
    {
        return $this->entity;
    }

    public function setEntity(string $entity): void
    {
        $this->entity = $entity;
    }

    public function getFolder(): ?MediaFolderEntity
    {
        return $this->folder;
    }

    public function setFolder(?MediaFolderEntity $folder): void
    {
        $this->folder = $folder;
    }
}

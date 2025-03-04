<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use League\Flysystem\Filesystem;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use Shopware\Core\Framework\Test\Filesystem\Adapter\MemoryAdapterFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Use this trait if your test operates with a filesystem
 */
trait FilesystemBehaviour
{
    public function getFilesystem(string $serviceId): Filesystem
    {
        /** @var Filesystem $filesystem */
        $filesystem = static::getContainer()->get($serviceId);

        return $filesystem;
    }

    public function getPublicFilesystem(): Filesystem
    {
        return $this->getFilesystem('shopware.filesystem.public');
    }

    public function getPrivateFilesystem(): Filesystem
    {
        return $this->getFilesystem('shopware.filesystem.private');
    }

    #[After]
    #[Before]
    public function removeWrittenFilesAfterFilesystemTests(): void
    {
        MemoryAdapterFactory::clearInstancesMemory();
    }

    abstract protected static function getContainer(): ContainerInterface;
}

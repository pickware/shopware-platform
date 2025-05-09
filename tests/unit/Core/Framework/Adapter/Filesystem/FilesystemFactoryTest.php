<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Filesystem;

use League\Flysystem\Visibility;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Filesystem\Adapter\LocalFactory;
use Shopware\Core\Framework\Adapter\Filesystem\FilesystemFactory;

/**
 * @internal
 */
#[CoversClass(FilesystemFactory::class)]
class FilesystemFactoryTest extends TestCase
{
    public function testMultipleSame(): void
    {
        static::expectExceptionObject(AdapterException::duplicateFilesystemFactory('local'));
        new FilesystemFactory([new LocalFactory(), new LocalFactory()]);
    }

    public function testCreateLocalAdapter(): void
    {
        $factory = new FilesystemFactory([new LocalFactory()]);
        $adapter = $factory->factory([
            'type' => 'local',
            'config' => [
                'root' => __DIR__,
                'options' => [
                    'visibility' => Visibility::PUBLIC,
                ],
            ],
        ]);

        static::assertSame(Visibility::PUBLIC, $adapter->visibility(''));
    }

    public function testCreateUnknown(): void
    {
        $factory = new FilesystemFactory([new LocalFactory()]);
        static::expectExceptionObject(AdapterException::filesystemFactoryNotFound('test2'));
        $factory->factory([
            'type' => 'test2',
        ]);
    }
}

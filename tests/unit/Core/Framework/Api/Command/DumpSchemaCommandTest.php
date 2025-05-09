<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\CachedEntitySchemaGenerator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Shopware\Core\Framework\Api\Command\DumpSchemaCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(DumpSchemaCommand::class)]
class DumpSchemaCommandTest extends TestCase
{
    public function testSimpleCall(): void
    {
        $definitionService = $this->createMock(DefinitionService::class);
        $definitionService->expects($this->once())->method('getSchema');
        $cache = $this->createMock(CacheInterface::class);
        $cmd = new DumpSchemaCommand($definitionService, $cache);

        $tmpFile = tempnam(sys_get_temp_dir(), 'schema');
        static::assertIsString($tmpFile);

        $cmd = new CommandTester($cmd);
        $cmd->execute(['outfile' => $tmpFile], ['capture_stderr_separately' => true]);

        $cmd->assertCommandIsSuccessful();
        static::assertFileExists($tmpFile, 'schema file not found');
        (new Filesystem())->remove($tmpFile);
    }

    public function testEntitySchema(): void
    {
        $definitionService = $this->createMock(DefinitionService::class);
        $definitionService->expects($this->once())->method('getSchema')->with(EntitySchemaGenerator::FORMAT, DefinitionService::API);
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('delete')->with(CachedEntitySchemaGenerator::CACHE_KEY);
        $cmd = new DumpSchemaCommand($definitionService, $cache);

        $cmd = new CommandTester($cmd);
        $cmd->execute(['outfile' => '/dev/null',  '--schema-format' => 'entity-schema']);

        $cmd->assertCommandIsSuccessful();
    }

    public function testOpenApiSchemaAdmin(): void
    {
        $definitionService = $this->createMock(DefinitionService::class);
        $definitionService->expects($this->once())->method('generate')->with('openapi-3', DefinitionService::API);
        $cache = $this->createMock(CacheInterface::class);
        $cmd = new DumpSchemaCommand($definitionService, $cache);

        $cmd = new CommandTester($cmd);
        $cmd->execute(['outfile' => '/dev/null', '--schema-format' => 'openapi3']);

        $cmd->assertCommandIsSuccessful();
    }

    public function testOpenApiSchemaStorefront(): void
    {
        $definitionService = $this->createMock(DefinitionService::class);
        $definitionService->expects($this->once())->method('generate')->with('openapi-3', DefinitionService::STORE_API);
        $cache = $this->createMock(CacheInterface::class);
        $cmd = new DumpSchemaCommand($definitionService, $cache);

        $cmd = new CommandTester($cmd);
        $cmd->execute(['outfile' => '/dev/null', '--schema-format' => 'openapi3', '--store-api' => true]);

        $cmd->assertCommandIsSuccessful();
    }
}

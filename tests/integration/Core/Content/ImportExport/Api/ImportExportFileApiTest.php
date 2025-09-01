<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\ImportExport\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportFile\ImportExportFileEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ImportExportFileApiTest extends TestCase
{
    use AdminApiTestBehaviour;
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<EntityCollection<ImportExportFileEntity>>
     */
    private EntityRepository $repository;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('import_export_file.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testImportExportFileCreateSuccess(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);

        foreach ($data as $entry) {
            $this->getBrowser()->jsonRequest('POST', $this->prepareRoute(), $entry);
            $response = $this->getBrowser()->getResponse();
            static::assertIsString($response->getContent());
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        }
        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');

        static::assertCount($num, $records);
        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['originalName'], $record['original_name']);
            static::assertSame($expect['path'], $record['path']);
            static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $record['expire_date']));
            static::assertSame($expect['size'], (int) $record['size']);
            static::assertSame($expect['accessToken'], $record['access_token']);
            unset($data[$record['id']]);
        }
    }

    public function testImportExportFileCreateMissingRequired(): void
    {
        $requiredProperties = ['originalName', 'path'];
        foreach ($requiredProperties as $property) {
            $entry = current($this->prepareImportExportFileTestData());
            unset($entry[$property]);
            $this->getBrowser()->jsonRequest('POST', $this->prepareRoute(), $entry);
            $response = $this->getBrowser()->getResponse();
            static::assertIsString($response->getContent());
            static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $response->getContent());
        }
    }

    public function testImportExportFileList(): void
    {
        foreach ([0, 5] as $num) {
            $data = $this->prepareImportExportFileTestData($num);
            if (!empty($data)) {
                $this->repository->create(array_values($data), $this->context);
            }

            $this->getBrowser()->jsonRequest('GET', $this->prepareRoute(), [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
            static::assertIsString($response->getContent());
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            $expectData = [];
            foreach (array_values($data) as $entry) {
                $expectData[$entry['id']] = $entry;
            }

            static::assertSame($num, $content['total']);
            for ($i = 0; $i < $num; ++$i) {
                $importExportFile = $content['data'][$i];
                $expect = $expectData[$importExportFile['_uniqueIdentifier']];
                static::assertSame($expect['originalName'], $importExportFile['originalName']);
                static::assertSame($expect['path'], $importExportFile['path']);
                static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $importExportFile['expireDate']));
                static::assertSame($expect['size'], $importExportFile['size']);
                static::assertSame($expect['accessToken'], $importExportFile['accessToken']);
            }
        }
    }

    public function testImportExportFileUpdateFull(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        $data = $this->rotateTestdata($data);

        $expectData = [];
        foreach ($ids as $idx => $id) {
            $expectData[$id] = $data[$idx];
            unset($data[$idx]['id']);

            $this->getBrowser()->jsonRequest('PATCH', $this->prepareRoute() . $id, $data[$idx], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
        }

        $this->getBrowser()->jsonRequest('GET', $this->prepareRoute(), [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertIsString($response->getContent());
        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($num, $content['total']);
        for ($i = 0; $i < $num; ++$i) {
            $importExportFile = $content['data'][$i];
            $expect = $expectData[$importExportFile['_uniqueIdentifier']];
            static::assertSame($expect['originalName'], $importExportFile['originalName']);
            static::assertSame($expect['path'], $importExportFile['path']);
            static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $importExportFile['expireDate']));
            static::assertSame($expect['size'], $importExportFile['size']);
            static::assertSame($expect['accessToken'], $importExportFile['accessToken']);
        }
    }

    public function testImportExportFileUpdateSuccessPartial(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        $data = $this->rotateTestdata($data);

        $properties = array_keys(current($data));
        $expectProperties = $properties;

        $expectData = [];
        foreach ($ids as $idx => $id) {
            $removedProperty = array_pop($properties);
            $expectData[$id] = $data[$idx];
            unset($data[$idx][$removedProperty]);
            unset($data[$idx]['id']);

            $this->getBrowser()->jsonRequest('PATCH', $this->prepareRoute() . $id, $data[$idx], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

            $this->getBrowser()->jsonRequest('GET', $this->prepareRoute() . $id, [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());

            static::assertIsString($response->getContent());
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

            $importExportFile = $content['data'];
            $expect = $expectData[$id];
            foreach ($expectProperties as $property) {
                if ($property === 'id') {
                    continue;
                }
                $currentValue = $importExportFile[$property];
                $expectValue = $expect[$property];
                if ($property === 'expireDate') {
                    $currentValue = strtotime((string) $currentValue);
                    $expectValue = strtotime((string) $expectValue);
                }
                if ($property === $removedProperty) {
                    static::assertNotSame($expectValue, $currentValue);
                } else {
                    static::assertSame($expectValue, $currentValue);
                }
            }
        }
    }

    public function testImportExportFileDetailSuccess(): void
    {
        $num = 2;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);

        foreach (array_values($data) as $expect) {
            $this->getBrowser()->jsonRequest('GET', $this->prepareRoute() . $expect['id'], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());

            static::assertIsString($response->getContent());
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame($expect['originalName'], $content['data']['originalName']);
            static::assertSame($expect['path'], $content['data']['path']);
            static::assertSame(strtotime((string) $expect['expireDate']), strtotime((string) $content['data']['expireDate']));
            static::assertSame($expect['size'], $content['data']['size']);
            static::assertSame($expect['accessToken'], $content['data']['accessToken']);
        }
    }

    public function testImportExportFileDetailNotFound(): void
    {
        $this->getBrowser()->jsonRequest('GET', $this->prepareRoute() . Uuid::randomHex(), [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testImportExportFileSearch(): void
    {
        $data = $this->prepareImportExportFileTestData(2);

        $invalidData = array_pop($data);

        $this->repository->create(array_values($data), $this->context);
        $searchData = array_pop($data);

        $filter = [];
        foreach ($searchData as $key => $value) {
            $filter['filter'][$key] = $invalidData[$key];
            $this->getBrowser()->jsonRequest('POST', $this->prepareRoute(true), $filter, [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
            static::assertIsString($response->getContent());
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame(0, $content['total']);

            $filter['filter'][$key] = $value;
            $this->getBrowser()->jsonRequest('POST', $this->prepareRoute(true), $filter, [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());
            static::assertIsString($response->getContent());
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame(1, $content['total']);
        }
    }

    public function testImportExportFileDelete(): void
    {
        $num = 3;
        $data = $this->prepareImportExportFileTestData($num);
        $this->repository->create(array_values($data), $this->context);
        $deleteId = array_column($data, 'id')[0];

        $this->getBrowser()->jsonRequest('DELETE', $this->prepareRoute() . Uuid::randomHex(), [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');
        static::assertCount($num, $records);

        $this->getBrowser()->jsonRequest('DELETE', $this->prepareRoute() . $deleteId, [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        $records = $this->connection->fetchAllAssociative('SELECT * FROM import_export_file');
        static::assertCount($num - 1, $records);
    }

    protected function prepareRoute(bool $search = false): string
    {
        $addPath = '';
        if ($search) {
            $addPath = '/search';
        }

        return '/api' . $addPath . '/import-export-file/';
    }

    /**
     * Prepare a defined number of test data.
     *
     * @return array<mixed>
     */
    protected function prepareImportExportFileTestData(int $num = 1, string $add = ''): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'originalName' => \sprintf('file%d.xml', $i),
                'path' => \sprintf('/test/%d/%s', $i, $add),
                'expireDate' => \sprintf('2011-01-01T15:03:%02d', $i),
                'size' => $i * 51,
                'accessToken' => Random::getBase64UrlString(32),
            ];
        }

        return $data;
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    protected function rotateTestdata(array $data): array
    {
        $data[] = array_shift($data);

        return array_values($data);
    }
}

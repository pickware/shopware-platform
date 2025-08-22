<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('after-sales')]
class MailHeaderFooterApiTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private EntityRepository $repository;

    private Connection $connection;

    private Context $context;

    protected function setUp(): void
    {
        $this->repository = static::getContainer()->get('mail_header_footer.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();

        try {
            $this->connection->executeStatement('DELETE FROM mail_header_footer');
        } catch (\Exception $e) {
            static::fail('Failed to remove testdata: ' . $e->getMessage());
        }
    }

    /**
     * api.mail_header_footer.create
     */
    #[Group('slow')]
    public function testHeaderFooterCreate(): void
    {
        // prepare test data
        $num = 5;
        $data = $this->prepareHeaderFooterTestData($num);

        // do API calls
        foreach ($data as $entry) {
            $this->getBrowser()->jsonRequest('POST', $this->prepareRoute(), $entry);
            $response = $this->getBrowser()->getResponse();
            static::assertIsString($response->getContent());
            static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        }

        // read created data from db
        $records = $this->connection->fetchAllAssociative(
            'SELECT *
             FROM mail_header_footer mhf
             JOIN mail_header_footer_translation mhft
                 ON mhf.id=mhft.mail_header_footer_id'
        );

        // compare expected and resulting data
        static::assertCount($num, $records);
        foreach ($records as $record) {
            $expect = $data[$record['id']];
            static::assertSame($expect['systemDefault'], (bool) $record['system_default']);
            static::assertSame($expect['name'], $record['name']);
            static::assertSame($expect['description'], $record['description']);
            static::assertSame($expect['headerHtml'], $record['header_html']);
            static::assertSame($expect['headerPlain'], $record['header_plain']);
            static::assertSame($expect['footerHtml'], $record['footer_html']);
            static::assertSame($expect['footerPlain'], $record['footer_plain']);
            unset($data[$record['id']]);
        }
    }

    /**
     * api.mail_header_footer.list
     */
    #[Group('slow')]
    public function testHeaderFooterList(): void
    {
        // Create test data.
        $num = 10;
        $data = $this->prepareHeaderFooterTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $this->getBrowser()->jsonRequest('GET', $this->prepareRoute(), [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertIsString($response->getContent());
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());

        $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        // Prepare expected data.
        $expectData = [];
        foreach ($data as $entry) {
            $expectData[$entry['id']] = $entry;
        }

        // compare expected and resulting data
        static::assertSame($num, $content['total']);
        for ($i = 0; $i < $num; ++$i) {
            $mailHeaderFooter = $content['data'][$i];
            $expect = $expectData[$mailHeaderFooter['_uniqueIdentifier']];
            static::assertSame($expect['systemDefault'], $mailHeaderFooter['systemDefault']);
            static::assertSame($expect['name'], $mailHeaderFooter['name']);
            static::assertSame($expect['description'], $mailHeaderFooter['description']);
            static::assertSame($expect['headerHtml'], $mailHeaderFooter['headerHtml']);
            static::assertSame($expect['headerPlain'], $mailHeaderFooter['headerPlain']);
            static::assertSame($expect['footerHtml'], $mailHeaderFooter['footerHtml']);
            static::assertSame($expect['footerPlain'], $mailHeaderFooter['footerPlain']);
        }
    }

    /**
     * api.mail_header_footer.update
     */
    public function testHeaderFooterUpdate(): void
    {
        // create test data
        $num = 10;
        $data = $this->prepareHeaderFooterTestData($num);
        $this->repository->create(array_values($data), $this->context);

        $ids = array_column($data, 'id');
        shuffle($data);

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

        // Compare expected and received data.
        static::assertSame($num, $content['total']);
        for ($i = 0; $i < $num; ++$i) {
            $mailHeaderFooter = $content['data'][$i];
            $expect = $expectData[$mailHeaderFooter['_uniqueIdentifier']];
            static::assertSame($expect['systemDefault'], $mailHeaderFooter['systemDefault']);
            static::assertSame($expect['name'], $mailHeaderFooter['name']);
            static::assertSame($expect['description'], $mailHeaderFooter['description']);
            static::assertSame($expect['headerHtml'], $mailHeaderFooter['headerHtml']);
            static::assertSame($expect['headerPlain'], $mailHeaderFooter['headerPlain']);
            static::assertSame($expect['footerHtml'], $mailHeaderFooter['footerHtml']);
            static::assertSame($expect['footerPlain'], $mailHeaderFooter['footerPlain']);
        }
    }

    /**
     * api.mail_header_footer.detail
     */
    public function testHeaderFooterDetail(): void
    {
        // create test data
        $num = 2;
        $data = $this->prepareHeaderFooterTestData($num);
        $this->repository->create(array_values($data), $this->context);

        foreach ($data as $expect) {
            // Request details
            $this->getBrowser()->jsonRequest('GET', $this->prepareRoute() . $expect['id'], [], [
                'HTTP_ACCEPT' => 'application/json',
            ]);
            $response = $this->getBrowser()->getResponse();
            static::assertSame(Response::HTTP_OK, $response->getStatusCode());

            static::assertIsString($response->getContent());
            $content = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
            static::assertSame($expect['systemDefault'], $content['data']['systemDefault']);
            static::assertSame($expect['name'], $content['data']['name']);
            static::assertSame($expect['description'], $content['data']['description']);
            static::assertSame($expect['headerHtml'], $content['data']['headerHtml']);
            static::assertSame($expect['headerPlain'], $content['data']['headerPlain']);
            static::assertSame($expect['footerHtml'], $content['data']['footerHtml']);
            static::assertSame($expect['footerPlain'], $content['data']['footerPlain']);
        }
    }

    /**
     * api.mail_header_footer.search
     */
    public function testHeaderFooterSearch(): void
    {
        // create test data
        $data = $this->prepareHeaderFooterTestData();
        $this->repository->create(array_values($data), $this->context);

        // Use last entry for search filters.
        $searchData = array_pop($data);
        static::assertIsArray($searchData);
        $filter = [];
        foreach ($searchData as $key => $value) {
            // Search call
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

    /**
     * api.mail_header_footer.delete
     */
    public function testHeaderFooterDelete(): void
    {
        // create test data
        $data = $this->prepareHeaderFooterTestData();
        $this->repository->create(array_values($data), $this->context);
        $deleteId = array_column($data, 'id')[0];

        // Test request
        $this->getBrowser()->jsonRequest('GET', $this->prepareRoute() . $deleteId, [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode());

        // Delete call
        $this->getBrowser()->jsonRequest('DELETE', $this->prepareRoute() . $deleteId, [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);
        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    protected function prepareRoute(bool $search = false): string
    {
        $addPath = '';
        if ($search) {
            $addPath = '/search';
        }

        return '/api' . $addPath . '/mail-header-footer/';
    }

    /**
     * Prepare a defined number of test data.
     *
     * @return array<string, array<string, mixed>>
     */
    private function prepareHeaderFooterTestData(int $num = 1, string $add = ''): array
    {
        $data = [];
        for ($i = 1; $i <= $num; ++$i) {
            $uuid = Uuid::randomHex();

            $data[Uuid::fromHexToBytes($uuid)] = [
                'id' => $uuid,
                'systemDefault' => $i % 2 !== 0,
                'name' => \sprintf('Test-Template %d %s', $i, $add),
                'description' => \sprintf('John Doe %d %s', $i, $add),
                'headerPlain' => \sprintf('Test header 123 %d %s', $i, $add),
                'headerHtml' => \sprintf('<h1>Test header %d %s </h1>', $i, $add),
                'footerPlain' => \sprintf('Test footer 123 %d %s', $i, $add),
                'footerHtml' => \sprintf('<h1>Test footer %d %s </h1>', $i, $add),
            ];
        }

        return $data;
    }
}

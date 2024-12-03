<?php

declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Flow\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Execution\FlowExecutionCollection;
use Shopware\Core\Content\Flow\FlowCollection;
use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Content\Flow\Subscriber\MostRecentFailedExecutionDateFieldCalculationSubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
#[CoversClass(MostRecentFailedExecutionDateFieldCalculationSubscriber::class)]
class MostRecentFailedExecutionFieldTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    /**
     * @var EntityRepository<FlowCollection>
     */
    private EntityRepository $flowRepository;

    /**
     * @var EntityRepository<FlowExecutionCollection>
     */
    private EntityRepository $flowExecutionRepository;

    protected function setUp(): void
    {
        $this->flowRepository = $this->getContainer()->get('flow.repository');
        $this->flowExecutionRepository = $this->getContainer()->get('flow_execution.repository');
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMostRecentFailedExecutionField(): void
    {
        $flowId = $this->flowRepository->create(
            [
                [
                    'name' => 'Test flow',
                    'eventName' => 'test.event',
                ],
            ],
            Context::createDefaultContext(),
        )->getPrimaryKeys('flow')[0];
        [$firstFlowExecutionId, $secondFlowExecutionId] = $this->flowExecutionRepository->create(
            [
                [
                    'flowId' => $flowId,
                    'eventData' => [],
                    'successful' => false,
                    'errorMessage' => 'Test error message',
                ],
                [
                    'flowId' => $flowId,
                    'eventData' => [],
                    'successful' => false,
                    'errorMessage' => 'Other test error message',
                ],
            ],
            Context::createDefaultContext(),
        )->getPrimaryKeys('flow_execution');

        $this->connection->executeStatement(
            'UPDATE `flow_execution` SET `created_at` = "2021-01-01 00:00:00" WHERE `id` = :id',
            ['id' => hex2bin($firstFlowExecutionId)],
        );

        $this->connection->executeStatement(
            'UPDATE `flow_execution` SET `created_at` = "2022-02-02 00:00:00" WHERE `id` = :id',
            ['id' => hex2bin($secondFlowExecutionId)],
        );

        /** @var FlowEntity $flow */
        $flow = $this->flowRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('id', $flowId))->addAssociation('executions'),
            Context::createDefaultContext(),
        )->first();

        static::assertEquals('2022-02-02 00:00:00', $flow->getMostRecentFailedExecutionDate()?->format('Y-m-d H:i:s'));
    }

    public function testMostRecentFailedExecutionFieldNullIfNoExecutions(): void
    {
        $flowId = $this->flowRepository->create(
            [
                [
                    'name' => 'Test flow',
                    'eventName' => 'test.event',
                ],
            ],
            Context::createDefaultContext(),
        )->getPrimaryKeys('flow')[0];

        /** @var FlowEntity $flow */
        $flow = $this->flowRepository->search(
            (new Criteria())->addFilter(new EqualsFilter('id', $flowId))->addAssociation('executions'),
            Context::createDefaultContext(),
        )->first();

        static::assertNull($flow->getMostRecentFailedExecutionDate());
    }
}

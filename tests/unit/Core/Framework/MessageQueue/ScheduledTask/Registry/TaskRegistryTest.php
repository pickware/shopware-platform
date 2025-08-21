<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Registry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTask;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskCollection;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Shopware\Tests\Unit\Core\Framework\MessageQueue\ScheduledTask\Scheduler\TestScheduledTask;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

/**
 * @internal
 */
#[CoversClass(TaskRegistry::class)]
class TaskRegistryTest extends TestCase
{
    /**
     * @var EntityRepository<ScheduledTaskCollection>&MockObject
     */
    private EntityRepository $scheduleTaskRepository;

    protected function setUp(): void
    {
        $this->scheduleTaskRepository = $this->createMock(EntityRepository::class);
    }

    public function testNewTasksAreCreated(): void
    {
        $tasks = [new TestScheduledTask(), new CreateAliasTask(), new CleanupCartTask()];
        $parameterBag = new ParameterBag([
            'shopware.test.active' => true,
            'elasticsearch.enabled' => false,
        ]);

        $registeredTask = new ScheduledTaskEntity();

        $registeredTask->setId('1');
        $registeredTask->setName(CleanupCartTask::getTaskName());
        $registeredTask->setRunInterval(CleanupCartTask::getDefaultInterval());
        $registeredTask->setDefaultRunInterval(CleanupCartTask::getDefaultInterval());
        $registeredTask->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $registeredTask->setNextExecutionTime(new \DateTimeImmutable());
        $registeredTask->setScheduledTaskClass(CleanupCartTask::class);

        /** @var StaticEntityRepository<ScheduledTaskCollection> $staticRepository */
        $staticRepository = new StaticEntityRepository([
            new ScheduledTaskCollection([$registeredTask]),
        ]);

        (new TaskRegistry($tasks, $staticRepository, $parameterBag))->registerTasks();

        static::assertSame(
            [
                [
                    [
                        'name' => TestScheduledTask::getTaskName(),
                        'scheduledTaskClass' => TestScheduledTask::class,
                        'runInterval' => TestScheduledTask::getDefaultInterval(),
                        'defaultRunInterval' => TestScheduledTask::getDefaultInterval(),
                        'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                    ],
                ],
                [
                    [
                        'name' => CreateAliasTask::getTaskName(),
                        'scheduledTaskClass' => CreateAliasTask::class,
                        'runInterval' => CreateAliasTask::getDefaultInterval(),
                        'defaultRunInterval' => CreateAliasTask::getDefaultInterval(),
                        'status' => ScheduledTaskDefinition::STATUS_SKIPPED,
                    ],
                ],
            ],
            $staticRepository->creates
        );
    }

    public function testInvalidTasksAreDeleted(): void
    {
        $parameterBag = new ParameterBag([]);

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, $parameterBag);

        $registeredTask = new ScheduledTaskEntity();

        $registeredTask->setId('deletedId');
        $registeredTask->setName(CleanupCartTask::getTaskName());
        $registeredTask->setRunInterval(CleanupCartTask::getDefaultInterval());
        $registeredTask->setDefaultRunInterval(CleanupCartTask::getDefaultInterval());
        $registeredTask->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $registeredTask->setNextExecutionTime(new \DateTimeImmutable());
        $registeredTask->setScheduledTaskClass('InvalidClass');
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$registeredTask]));
        $this->scheduleTaskRepository->expects($this->once())->method('search')->willReturn($result);
        $this->scheduleTaskRepository->expects($this->never())->method('update');
        $this->scheduleTaskRepository->expects($this->never())->method('create');
        $this->scheduleTaskRepository->expects($this->once())->method('delete')->with([
            [
                'id' => 'deletedId',
            ],
        ], Context::createDefaultContext());

        $registry->registerTasks();
    }

    public function testQueuedOrScheduledTasksShouldBecomeSkipped(): void
    {
        $tasks = [new TestScheduledTask(), new CreateAliasTask()];

        // passing these parameters so these task shouldRun return false
        $parameterBag = new ParameterBag([
            'shopware.test.active' => false,
            'elasticsearch.enabled' => false,
        ]);

        $registry = new TaskRegistry($tasks, $this->scheduleTaskRepository, $parameterBag);

        $queuedTask = new ScheduledTaskEntity();
        $scheduledTask = new ScheduledTaskEntity();

        $queuedTask->setId('queuedTask');
        $queuedTask->setName(TestScheduledTask::getTaskName());
        $queuedTask->setRunInterval(TestScheduledTask::getDefaultInterval());
        $queuedTask->setDefaultRunInterval(TestScheduledTask::getDefaultInterval());
        $queuedTask->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);
        $queuedTask->setNextExecutionTime(new \DateTimeImmutable());
        $queuedTask->setScheduledTaskClass(TestScheduledTask::class);

        $scheduledTask->setId('scheduledTask');
        $scheduledTask->setName(CreateAliasTask::getTaskName());
        $scheduledTask->setRunInterval(CreateAliasTask::getDefaultInterval());
        $scheduledTask->setDefaultRunInterval(CreateAliasTask::getDefaultInterval());
        $scheduledTask->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $scheduledTask->setNextExecutionTime(new \DateTimeImmutable());
        $scheduledTask->setScheduledTaskClass(CreateAliasTask::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$queuedTask, $scheduledTask]));

        $this->scheduleTaskRepository->expects($this->once())->method('search')->willReturn($result);

        $this->scheduleTaskRepository->expects($this->exactly(1))->method('update')->willReturnCallback(function (array $data, Context $context) {
            static::assertCount(2, $data);

            static::assertNotEmpty($data[0]);
            static::assertNotEmpty($data[1]);

            [$queueTaskPayload, $scheduledTaskPayload] = $data;

            static::assertArrayHasKey('status', $queueTaskPayload);
            static::assertArrayHasKey('status', $scheduledTaskPayload);
            static::assertArrayHasKey('id', $queueTaskPayload);
            static::assertArrayHasKey('id', $scheduledTaskPayload);
            static::assertSame(ScheduledTaskDefinition::STATUS_SKIPPED, $queueTaskPayload['status']);
            static::assertSame('queuedTask', $queueTaskPayload['id']);
            static::assertSame(ScheduledTaskDefinition::STATUS_SKIPPED, $scheduledTaskPayload['status']);
            static::assertSame('scheduledTask', $scheduledTaskPayload['id']);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->scheduleTaskRepository->expects($this->never())->method('delete');
        $this->scheduleTaskRepository->expects($this->never())->method('create');

        $registry->registerTasks();
    }

    public function testQueuedOrSkippedTasksShouldBecomeScheduled(): void
    {
        $tasks = [new TestScheduledTask(), new CreateAliasTask()];

        // passing these parameters so these task shouldRun return true
        $parameterBag = new ParameterBag([
            'shopware.test.active' => true,
            'elasticsearch.enabled' => true,
        ]);

        $registry = new TaskRegistry($tasks, $this->scheduleTaskRepository, $parameterBag);

        $queuedTask = new ScheduledTaskEntity();
        $skippedTask = new ScheduledTaskEntity();

        $queuedTask->setId('queuedTask');
        $queuedTask->setName(TestScheduledTask::getTaskName());
        $queuedTask->setRunInterval(TestScheduledTask::getDefaultInterval());
        $queuedTask->setDefaultRunInterval(TestScheduledTask::getDefaultInterval());
        $queuedTask->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);
        $queuedTask->setNextExecutionTime(new \DateTimeImmutable());
        $queuedTask->setScheduledTaskClass(TestScheduledTask::class);

        $skippedTask->setId('skippedTask');
        $skippedTask->setName(CreateAliasTask::getTaskName());
        $skippedTask->setRunInterval(CreateAliasTask::getDefaultInterval());
        $skippedTask->setDefaultRunInterval(CreateAliasTask::getDefaultInterval());
        $skippedTask->setStatus(ScheduledTaskDefinition::STATUS_SKIPPED);
        $skippedTask->setNextExecutionTime(new \DateTimeImmutable());
        $skippedTask->setScheduledTaskClass(CreateAliasTask::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$queuedTask, $skippedTask]));

        $this->scheduleTaskRepository->expects($this->once())->method('search')->willReturn($result);

        $this->scheduleTaskRepository->expects($this->exactly(1))->method('update')->willReturnCallback(function (array $data, Context $context) {
            static::assertCount(2, $data);

            static::assertNotEmpty($data[0]);
            static::assertNotEmpty($data[1]);

            [$queueTaskPayload, $skippedTaskPayload] = $data;

            static::assertArrayHasKey('status', $queueTaskPayload);
            static::assertArrayHasKey('status', $skippedTaskPayload);
            static::assertArrayHasKey('id', $queueTaskPayload);
            static::assertArrayHasKey('id', $skippedTaskPayload);
            static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $queueTaskPayload['status']);
            static::assertSame('queuedTask', $queueTaskPayload['id']);
            static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $skippedTaskPayload['status']);
            static::assertSame('skippedTask', $skippedTaskPayload['id']);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->scheduleTaskRepository->expects($this->never())->method('delete');
        $this->scheduleTaskRepository->expects($this->never())->method('create');

        $registry->registerTasks();
    }

    public function testDefaultRunIntervalIsUpdatedIfItChanged(): void
    {
        $tasks = [new CleanupCartTask()];

        $registry = new TaskRegistry($tasks, $this->scheduleTaskRepository, new ParameterBag([]));

        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('cleanupTask');
        $taskEntity->setName(CleanupCartTask::getTaskName());
        $taskEntity->setRunInterval(10);
        $taskEntity->setDefaultRunInterval(20);
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $taskEntity->setNextExecutionTime(new \DateTimeImmutable());
        $taskEntity->setScheduledTaskClass(CleanupCartTask::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$taskEntity]));

        $this->scheduleTaskRepository->expects($this->once())->method('search')->willReturn($result);

        $this->scheduleTaskRepository->expects($this->exactly(1))->method('update')->willReturnCallback(function (array $data, Context $context) {
            static::assertCount(1, $data);

            static::assertNotEmpty($data[0]);

            static::assertSame('cleanupTask', $data[0]['id']);
            static::assertSame(CleanupCartTask::getDefaultInterval(), $data[0]['defaultRunInterval']);
            static::assertArrayNotHasKey('runInterval', $data[0]);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->scheduleTaskRepository->expects($this->never())->method('delete');
        $this->scheduleTaskRepository->expects($this->never())->method('create');

        $registry->registerTasks();
    }

    public function testRunIntervalIsUpdatedIfItMatchesDefault(): void
    {
        $tasks = [new CleanupCartTask()];

        $registry = new TaskRegistry($tasks, $this->scheduleTaskRepository, new ParameterBag([]));

        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('cleanupTask');
        $taskEntity->setName(CleanupCartTask::getTaskName());
        $taskEntity->setRunInterval(10);
        $taskEntity->setDefaultRunInterval(10);
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);
        $taskEntity->setNextExecutionTime(new \DateTimeImmutable());
        $taskEntity->setScheduledTaskClass(CleanupCartTask::class);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$taskEntity]));

        $this->scheduleTaskRepository->expects($this->once())->method('search')->willReturn($result);

        $this->scheduleTaskRepository->expects($this->exactly(1))->method('update')->willReturnCallback(function (array $data, Context $context) {
            static::assertCount(1, $data);

            static::assertNotEmpty($data[0]);

            static::assertSame('cleanupTask', $data[0]['id']);
            static::assertSame(CleanupCartTask::getDefaultInterval(), $data[0]['defaultRunInterval']);
            static::assertSame(CleanupCartTask::getDefaultInterval(), $data[0]['runInterval']);

            return new EntityWrittenContainerEvent($context, new NestedEventCollection(), []);
        });

        $this->scheduleTaskRepository->expects($this->never())->method('delete');
        $this->scheduleTaskRepository->expects($this->never())->method('create');

        $registry->registerTasks();
    }

    public function testListAllTasks(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('cleanupTask');
        $taskEntity->setName('foo');

        /** @var StaticEntityRepository<ScheduledTaskCollection> $repository */
        $repository = new StaticEntityRepository([new ScheduledTaskCollection([$taskEntity])]);

        $tasks = (new TaskRegistry([], $repository, new ParameterBag([])))->getAllTasks(Context::createDefaultContext());

        static::assertCount(1, $tasks);
        static::assertSame($taskEntity, $tasks->first());
    }

    public function testScheduleTaskSuccessfully(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturnOnConsecutiveCalls(
            new ScheduledTaskCollection([$taskEntity]),
            new ScheduledTaskCollection([$taskEntity])
        );
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('update')
            ->with(
                [[
                    'id' => 'test-task-id',
                    'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                ]],
                static::isInstanceOf(Context::class)
            );

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->scheduleTask('test.task', false, false, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $status);
    }

    public function testScheduleTaskWithImmediatelyOption(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_SCHEDULED);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturnOnConsecutiveCalls(
            new ScheduledTaskCollection([$taskEntity]),
            new ScheduledTaskCollection([$taskEntity])
        );
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('update')
            ->with(
                static::callback(function (array $data) {
                    static::assertCount(1, $data);
                    static::assertSame('test-task-id', $data[0]['id']);
                    static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $data[0]['status']);
                    static::assertInstanceOf(\DateTimeImmutable::class, $data[0]['nextExecutionTime']);

                    return true;
                }),
                static::isInstanceOf(Context::class)
            );

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->scheduleTask('test.task', true, false, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_SCHEDULED, $status);
    }

    public function testScheduleTaskFailsWhenRunningWithoutForce(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_RUNNING);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$taskEntity]));
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->never())
            ->method('update');

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->scheduleTask('test.task', false, false, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $status);
    }

    public function testScheduleTaskFailsWhenQueuedWithoutForce(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$taskEntity]));
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->never())
            ->method('update');

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->scheduleTask('test.task', false, false, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_QUEUED, $status);
    }

    public function testScheduleTaskSucceedsWhenRunningWithForce(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_RUNNING);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturnOnConsecutiveCalls(
            new ScheduledTaskCollection([$taskEntity]),
            new ScheduledTaskCollection([$taskEntity])
        );
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('update')
            ->with(
                [[
                    'id' => 'test-task-id',
                    'status' => ScheduledTaskDefinition::STATUS_SCHEDULED,
                ]],
                static::isInstanceOf(Context::class)
            );

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->scheduleTask('test.task', false, true, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $status);
    }

    public function testScheduleTaskThrowsExceptionWhenTaskNotFound(): void
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([]));
        $result->method('first')->willReturn(null);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tried to fetch "non.existing.task" scheduled task, but scheduled task does not exist');

        $registry->scheduleTask('non.existing.task', false, false, Context::createDefaultContext());
    }

    public function testDeactivateTaskSuccessfully(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_INACTIVE);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturnOnConsecutiveCalls(
            new ScheduledTaskCollection([$taskEntity]),
            new ScheduledTaskCollection([$taskEntity])
        );
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('update')
            ->with(
                [[
                    'id' => 'test-task-id',
                    'status' => ScheduledTaskDefinition::STATUS_INACTIVE,
                ]],
                static::isInstanceOf(Context::class)
            );

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->deactivateTask('test.task', false, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_INACTIVE, $status);
    }

    public function testDeactivateTaskFailsWhenRunningWithoutForce(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_RUNNING);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$taskEntity]));
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->never())
            ->method('update');

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->deactivateTask('test.task', false, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $status);
    }

    public function testDeactivateTaskFailsWhenQueuedWithoutForce(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_QUEUED);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([$taskEntity]));
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->never())
            ->method('update');

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->deactivateTask('test.task', false, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_QUEUED, $status);
    }

    public function testDeactivateTaskSucceedsWhenRunningWithForce(): void
    {
        $taskEntity = new ScheduledTaskEntity();
        $taskEntity->setId('test-task-id');
        $taskEntity->setName('test.task');
        $taskEntity->setStatus(ScheduledTaskDefinition::STATUS_RUNNING);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturnOnConsecutiveCalls(
            new ScheduledTaskCollection([$taskEntity]),
            new ScheduledTaskCollection([$taskEntity])
        );
        $result->method('first')->willReturn($taskEntity);

        $this->scheduleTaskRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn($result);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('update')
            ->with(
                [[
                    'id' => 'test-task-id',
                    'status' => ScheduledTaskDefinition::STATUS_INACTIVE,
                ]],
                static::isInstanceOf(Context::class)
            );

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));
        $status = $registry->deactivateTask('test.task', true, Context::createDefaultContext());

        static::assertSame(ScheduledTaskDefinition::STATUS_RUNNING, $status);
    }

    public function testDeactivateTaskThrowsExceptionWhenTaskNotFound(): void
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new ScheduledTaskCollection([]));
        $result->method('first')->willReturn(null);

        $this->scheduleTaskRepository->expects($this->once())
            ->method('search')
            ->willReturn($result);

        $registry = new TaskRegistry([], $this->scheduleTaskRepository, new ParameterBag([]));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tried to fetch "non.existing.task" scheduled task, but scheduled task does not exist');

        $registry->deactivateTask('non.existing.task', false, Context::createDefaultContext());
    }
}

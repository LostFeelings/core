<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\ScheduledTask\Registry;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RequeueDeadMessagesTask;
use Shopware\Core\Framework\ScheduledTask\Registry\TaskRegistry;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskDefinition;
use Shopware\Core\Framework\ScheduledTask\ScheduledTaskEntity;
use Shopware\Core\Framework\Test\MessageQueue\fixtures\TestMessage;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TaskRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $scheduledTaskRepo;

    /**
     * @var TaskRegistry
     */
    private $registry;

    public function setUp(): void
    {
        $this->scheduledTaskRepo = $this->getContainer()->get('scheduled_task.repository');

        $this->registry = new TaskRegistry(
            [
                new RequeueDeadMessagesTask(),
            ],
            $this->scheduledTaskRepo
        );
    }

    public function testOnNonRegisteredTask()
    {
        $connection = $this->getContainer()->get(Connection::class);
        $connection->exec('DELETE FROM scheduled_task');

        $this->registry->registerTasks();

        $tasks = $this->scheduledTaskRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(1, $tasks);
        /** @var ScheduledTaskEntity $task */
        $task = $tasks->first();
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertEquals(RequeueDeadMessagesTask::class, $task->getScheduledTaskClass());
        static::assertEquals(RequeueDeadMessagesTask::getDefaultInterval(), $task->getRunInterval());
        static::assertEquals(RequeueDeadMessagesTask::getTaskName(), $task->getName());
        static::assertEquals(ScheduledTaskDefinition::STATUS_SCHEDULED, $task->getStatus());
    }

    public function testOnAlreadyRegisteredTask()
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('scheduledTaskClass', RequeueDeadMessagesTask::class));
        /** @var ScheduledTaskEntity $alreadyRegistered */
        $alreadyRegistered = $this->scheduledTaskRepo->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(ScheduledTaskEntity::class, $alreadyRegistered);

        $this->scheduledTaskRepo->update([
            [
                'id' => $alreadyRegistered->getId(),
                'name' => 'test',
                'runInterval' => 5,
                'status' => ScheduledTaskDefinition::STATUS_FAILED,
            ],
        ], Context::createDefaultContext());

        $this->registry->registerTasks();

        $tasks = $this->scheduledTaskRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(1, $tasks);
        /** @var ScheduledTaskEntity $task */
        $task = $tasks->first();
        static::assertInstanceOf(ScheduledTaskEntity::class, $task);
        static::assertEquals(RequeueDeadMessagesTask::class, $task->getScheduledTaskClass());
        static::assertEquals(5, $task->getRunInterval());
        static::assertEquals('test', $task->getName());
        static::assertEquals(ScheduledTaskDefinition::STATUS_FAILED, $task->getStatus());
    }

    public function testWithWrongClass()
    {
        static::expectException(\RuntimeException::class);
        static::expectExceptionMessage(sprintf(
            'Tried to register "%s" as scheduled task, but class does not implement ScheduledTaskInterface',
            TestMessage::class
        ));
        $registry = new TaskRegistry(
            [
                new TestMessage(),
            ],
            $this->scheduledTaskRepo
        );

        $registry->registerTasks();
    }

    public function testItDeletesNotAvailableTasks()
    {
        $tasks = $this->scheduledTaskRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertTrue(count($tasks) > 0);

        $registry = new TaskRegistry([], $this->scheduledTaskRepo);
        $registry->registerTasks();

        $tasks = $this->scheduledTaskRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();

        static::assertCount(0, $tasks);
    }
}

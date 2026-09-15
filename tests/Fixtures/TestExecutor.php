<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests\Fixtures;

use A2A\Security\CallContext;
use A2A\Server\{Executor, Lifecycle};
use Lf\A2a\V1\{SendMessageRequest, StreamResponse, Task, TaskState, TaskStatusUpdateEvent};

final class TestExecutor implements Executor
{
    public function execute(SendMessageRequest $request, Task $task, CallContext $context): iterable
    {
        yield (new StreamResponse())->setStatusUpdate((new TaskStatusUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setStatus(Lifecycle::status(TaskState::TASK_STATE_COMPLETED)));
    }
}

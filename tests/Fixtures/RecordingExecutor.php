<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests\Fixtures;

use A2A\Security\CallContext;
use A2A\Server\{Executor, Lifecycle};
use Lf\A2a\V1\{Artifact, Message, Part, Role, SendMessageRequest, StreamResponse, Task, TaskArtifactUpdateEvent, TaskState, TaskStatusUpdateEvent};

final class RecordingExecutor implements Executor
{
    public function execute(SendMessageRequest $request, Task $task, CallContext $context): iterable
    {
        $text = $request->getMessage()?->getParts()[0]?->getText() ?? '';
        if ($text === 'fail') {
            throw new \RuntimeException('private execution detail');
        }
        $artifact = (new Artifact())->setArtifactId('answer')->setParts([(new Part())->setText($text)]);
        yield (new StreamResponse())->setArtifactUpdate((new TaskArtifactUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setArtifact($artifact)->setLastChunk(true));
        $state = $text === 'pause' ? TaskState::TASK_STATE_INPUT_REQUIRED : TaskState::TASK_STATE_COMPLETED;
        $status = Lifecycle::status($state);
        $status->setMessage((new Message())->setMessageId('response-'.$request->getMessage()?->getMessageId())->setContextId($task->getContextId())->setTaskId($task->getId())->setRole(Role::ROLE_AGENT)->setParts([(new Part())->setText('processed')]));
        yield (new StreamResponse())->setStatusUpdate((new TaskStatusUpdateEvent())->setTaskId($task->getId())->setContextId($task->getContextId())->setStatus($status));
    }
}

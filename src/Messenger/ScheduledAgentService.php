<?php

declare(strict_types=1);

namespace A2A\Bundle\Messenger;

use A2A\Server\{AgentService, TaskService};
use A2A\Security\CallContext;
use Symfony\Component\Messenger\{Envelope, MessageBusInterface};
use Symfony\Component\Messenger\Transport\Sender\SendersLocatorInterface;
use Symfony\Component\Messenger\Transport\Sync\SyncTransport;

final readonly class ScheduledAgentService implements AgentService
{
    public function __construct(private TaskService $service, private MessageBusInterface $bus, private SendersLocatorInterface $senders)
    {
    }
    public function sendMessage(\Lf\A2a\V1\SendMessageRequest $request, CallContext $context): \Lf\A2a\V1\SendMessageResponse
    {
        if ($request->getConfiguration()?->getReturnImmediately()) {
            $envelope = new Envelope(new ProcessQueue());
            $found = false;
            foreach ($this->senders->getSenders($envelope) as $sender) {
                if ($sender instanceof SyncTransport) {
                    throw new \LogicException('A2A background jobs require an asynchronous Messenger transport');
                }
                $found = true;
            }
            if (!$found) {
                throw new \LogicException('Configure Messenger routing for ProcessQueue to an asynchronous transport');
            }
            $result = $this->service->sendMessage($request, $context);
            $this->bus->dispatch($envelope);
            return $result;
        }
        return $this->service->sendMessage($request, $context);
    }
    /** @return iterable<\Lf\A2a\V1\StreamResponse> */
    public function sendStreamingMessage(\Lf\A2a\V1\SendMessageRequest $request, CallContext $context): iterable
    {
        return $this->service->sendStreamingMessage($request, $context);
    }
    public function getTask(\Lf\A2a\V1\GetTaskRequest $request, CallContext $context): \Lf\A2a\V1\Task
    {
        return $this->service->getTask($request, $context);
    }
    public function listTasks(\Lf\A2a\V1\ListTasksRequest $request, CallContext $context): \Lf\A2a\V1\ListTasksResponse
    {
        return $this->service->listTasks($request, $context);
    }
    public function cancelTask(\Lf\A2a\V1\CancelTaskRequest $request, CallContext $context): \Lf\A2a\V1\Task
    {
        return $this->service->cancelTask($request, $context);
    }
    /** @return iterable<\Lf\A2a\V1\StreamResponse> */
    public function subscribeToTask(\Lf\A2a\V1\SubscribeToTaskRequest $request, CallContext $context): iterable
    {
        return $this->service->subscribeToTask($request, $context);
    }
    public function createTaskPushNotificationConfig(\Lf\A2a\V1\TaskPushNotificationConfig $request, CallContext $context): \Lf\A2a\V1\TaskPushNotificationConfig
    {
        return $this->service->createTaskPushNotificationConfig($request, $context);
    }
    public function getTaskPushNotificationConfig(\Lf\A2a\V1\GetTaskPushNotificationConfigRequest $request, CallContext $context): \Lf\A2a\V1\TaskPushNotificationConfig
    {
        return $this->service->getTaskPushNotificationConfig($request, $context);
    }
    public function listTaskPushNotificationConfigs(\Lf\A2a\V1\ListTaskPushNotificationConfigsRequest $request, CallContext $context): \Lf\A2a\V1\ListTaskPushNotificationConfigsResponse
    {
        return $this->service->listTaskPushNotificationConfigs($request, $context);
    }
    public function deleteTaskPushNotificationConfig(\Lf\A2a\V1\DeleteTaskPushNotificationConfigRequest $request, CallContext $context): \Google\Protobuf\GPBEmpty
    {
        return $this->service->deleteTaskPushNotificationConfig($request, $context);
    }
    public function getExtendedAgentCard(\Lf\A2a\V1\GetExtendedAgentCardRequest $request, CallContext $context): \Lf\A2a\V1\AgentCard
    {
        return $this->service->getExtendedAgentCard($request, $context);
    }
}

<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests;

use A2A\Bundle\Messenger\ProcessQueueHandler;
use A2A\Bundle\Tests\Fixtures\TestKernel;
use A2A\Protocol\Json;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

final class KernelTest extends TestCase
{
    public function testDiscoveryAndRealRoutes(): void
    {
        $kernel = new TestKernel();
        $browser = new KernelBrowser($kernel);
        $browser->disableReboot();
        try {
            $browser->request('GET', '/.well-known/agent-card.json');
            self::assertSame(200, $browser->getResponse()->getStatusCode());
            $card = Json::object($browser->getResponse()->getContent());
            self::assertCount(2, $card->supportedInterfaces);
            self::assertFalse($kernel->getContainer()->get('test.grpc_options')->enabled);
            self::assertInstanceOf(\A2A\Client\Client::class, $kernel->getContainer()->get('a2a.client.peer'));
            $browser->request('POST', '/a2a/rpc', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer test-token'], content: '{"jsonrpc":"2.0","id":1,"method":"SendMessage","params":{"message":{"messageId":"test","role":"ROLE_USER","parts":[{"text":"hello"}]}}}');
            self::assertSame(200, $browser->getResponse()->getStatusCode());
            $task = Json::object($browser->getResponse()->getContent())->result->task;
            self::assertSame('TASK_STATE_COMPLETED', $task->status->state);
            $browser->request('GET', '/a2a/tasks/'.$task->id, server: ['HTTP_AUTHORIZATION' => 'Bearer test-token']);
            self::assertSame($task->id, Json::object($browser->getResponse()->getContent())->id);
            $browser->request('GET', '/a2a/tasks/'.$task->id);
            self::assertSame(401, $browser->getResponse()->getStatusCode());
        } finally {
            $kernel->shutdown();
        }
    }
    public function testGrpcOptInAdvertisesThirdTransport(): void
    {
        $kernel = new TestKernel(grpc: true);
        $browser = new KernelBrowser($kernel);
        try {
            $browser->request('GET', '/.well-known/agent-card.json');
            self::assertSame(200, $browser->getResponse()->getStatusCode());
            self::assertCount(3, Json::object($browser->getResponse()->getContent())->supportedInterfaces);
            self::assertTrue($kernel->getContainer()->get('test.grpc_options')->enabled);
        } finally {
            $kernel->shutdown();
        }
    }
    public function testMessengerPersistsAndExecutesBackgroundTask(): void
    {
        $kernel = new TestKernel(messenger: true);
        $browser = new KernelBrowser($kernel);
        $browser->disableReboot();
        try {
            $browser->request('POST', '/a2a/message:send', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer test-token'], content: '{"message":{"messageId":"async","role":"ROLE_USER","parts":[{"text":"hello"}]},"configuration":{"returnImmediately":true}}');
            self::assertSame(200, $browser->getResponse()->getStatusCode());
            $task = Json::object($browser->getResponse()->getContent())->task;
            self::assertSame('TASK_STATE_SUBMITTED', $task->status->state);
            $container = $kernel->getContainer()->get('test.service_container');
            $messages = $container->get('messenger.transport.async')->getSent();
            self::assertCount(1, $messages);
            ($container->get(ProcessQueueHandler::class))($messages[0]->getMessage());
            $browser->request('GET', '/a2a/tasks/'.$task->id, server: ['HTTP_AUTHORIZATION' => 'Bearer test-token']);
            self::assertSame('TASK_STATE_COMPLETED', Json::object($browser->getResponse()->getContent())->status->state);
        } finally {
            $kernel->shutdown();
        }
    }
    public function testDoctrineAdapterThroughKernel(): void
    {
        $kernel = new TestKernel(doctrine: true);
        $kernel->boot();
        try {
            $repository = $kernel->getContainer()->get('test.repository');
            $repository->initializeSchema();
            $browser = new KernelBrowser($kernel);
            $browser->disableReboot();
            $browser->request('POST', '/a2a/message:send', server: ['CONTENT_TYPE' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer test-token'], content: '{"message":{"messageId":"db","role":"ROLE_USER","parts":[{"text":"hello"}]}}');
            self::assertSame(200, $browser->getResponse()->getStatusCode());
            self::assertSame('TASK_STATE_COMPLETED', Json::object($browser->getResponse()->getContent())->task->status->state);
        } finally {
            $kernel->shutdown();
        }
    }
}

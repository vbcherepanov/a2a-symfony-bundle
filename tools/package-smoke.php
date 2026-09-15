<?php

declare(strict_types=1);

use A2A\Bundle\Tests\Fixtures\TestKernel;
use Symfony\Component\HttpFoundation\Request;

require $argv[1];
require dirname(__DIR__).'/tests/Fixtures/TestExecutor.php';
require dirname(__DIR__).'/tests/Fixtures/TestKernel.php';

if (extension_loaded('grpc') || extension_loaded('openswoole') || class_exists(Grpc\BaseStub::class)) {
    throw new RuntimeException('Package smoke check must run without native gRPC support');
}
$kernel = new TestKernel();
try {
    $response = $kernel->handle(Request::create('/.well-known/agent-card.json'));
    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException('Installed bundle did not expose the agent card');
    }
    $request = Request::create('/a2a/rpc', 'POST', server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_AUTHORIZATION' => 'Bearer test-token',
    ], content: '{"jsonrpc":"2.0","id":1,"method":"SendMessage","params":{"message":{"messageId":"package-check","role":"ROLE_USER","parts":[{"text":"hello"}]}}}');
    $response = $kernel->handle($request);
    $body = json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR);
    if ($response->getStatusCode() !== 200 || ($body['result']['task']['status']['state'] ?? null) !== 'TASK_STATE_COMPLETED') {
        throw new RuntimeException('Installed bundle did not complete the JSON-RPC task');
    }
    fwrite(STDOUT, "Installed bundle served discovery and completed a task without native gRPC support.\n");
} finally {
    $kernel->shutdown();
}

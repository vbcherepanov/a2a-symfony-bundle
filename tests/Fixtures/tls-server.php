<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = new A2A\Bundle\Tests\Fixtures\Application(getenv('A2A_TEST_STORAGE'));
$options = new A2A\Transport\Grpc\GrpcServerOptions(
    enabled: true,
    port: (int) getenv('A2A_TEST_TLS_PORT'),
    workers: 1,
    certificateFile: getenv('A2A_TEST_TLS_CERTIFICATE'),
    privateKeyFile: getenv('A2A_TEST_TLS_KEY'),
    clientCaFile: getenv('A2A_TEST_TLS_CA'),
    requireClientCertificate: getenv('A2A_TEST_TLS_MUTUAL') === '1',
);
if (getenv('A2A_TEST_TLS_BINDING') === 'grpc') {
    (new A2A\Transport\Grpc\OpenSwooleRuntime($app->grpc, $options, $app->options, new Psr\Log\NullLogger()))->serve();
    exit;
}

$server = new OpenSwoole\Http\Server($options->host, $options->port, OpenSwoole\Server::POOL_MODE, OpenSwoole\Constant::SOCK_TCP | OpenSwoole\Constant::SSL);
$server->set([
    'worker_num' => 1,
    'ssl_cert_file' => $options->certificateFile,
    'ssl_key_file' => $options->privateKeyFile,
    'ssl_client_cert_file' => $options->clientCaFile,
    'ssl_verify_peer' => $options->requireClientCertificate,
    'ssl_allow_self_signed' => false,
    'log_level' => OpenSwoole\Constant::LOG_WARNING,
]);
$server->on('request', static function (OpenSwoole\Http\Request $request, OpenSwoole\Http\Response $response) use ($app): void {
    $result = $app->endpoint->handle(new A2A\Transport\Http\Request($request->server['request_method'], $request->server['request_uri'], $request->getContent(), $request->header));
    $response->status($result->status);
    foreach ($result->headers as $name => $value) {
        $response->header($name, $value);
    }
    if (is_string($result->body)) {
        $response->end($result->body);
        return;
    }
    foreach ($result->body as $chunk) {
        $response->write($chunk);
    }
    $response->end();
});
$server->start();

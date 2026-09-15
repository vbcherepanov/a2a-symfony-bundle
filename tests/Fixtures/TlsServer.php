<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests\Fixtures;

use Symfony\Component\Process\Process;

final class TlsServer
{
    private const START_ATTEMPTS = 100;
    private const POLL_MICROSECONDS = 20000;
    public readonly int $port;
    private Process $process;

    public function __construct(TlsCertificates $certificates, string $binding, bool $mutual = false, string $certificate = 'server')
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        if ($socket === false) {
            throw new \RuntimeException('Cannot allocate test server port');
        }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($address === false) {
            throw new \RuntimeException('Cannot read test server port');
        }
        $this->port = (int) substr($address, strrpos($address, ':') + 1);
        $this->process = new Process(['php', __DIR__.'/tls-server.php'], env: [
            'A2A_TEST_STORAGE' => $certificates->path('storage-'.bin2hex(random_bytes(8))),
            'A2A_TEST_TLS_PORT' => (string) $this->port,
            'A2A_TEST_TLS_BINDING' => $binding,
            'A2A_TEST_TLS_CERTIFICATE' => $certificates->path($certificate.'.pem'),
            'A2A_TEST_TLS_KEY' => $certificates->path($certificate.'.key'),
            'A2A_TEST_TLS_CA' => $certificates->path('ca.pem'),
            'A2A_TEST_TLS_MUTUAL' => $mutual ? '1' : '0',
        ]);
        $this->process->start();
        for ($attempt = 0; $attempt < self::START_ATTEMPTS; ++$attempt) {
            if (!$this->process->isRunning()) {
                throw new \RuntimeException($this->process->getErrorOutput().$this->process->getOutput());
            }
            $socket = @fsockopen('127.0.0.1', $this->port, $errno, $error, 0.05);
            if (is_resource($socket)) {
                fclose($socket);
                return;
            }
            usleep(self::POLL_MICROSECONDS);
        }
        $this->stop();
        throw new \RuntimeException('TLS test server did not start');
    }

    public function endpoint(string $binding): string
    {
        return match ($binding) {
            'grpc' => '127.0.0.1:'.$this->port,
            'jsonrpc' => 'https://127.0.0.1:'.$this->port.'/a2a/rpc',
            'rest' => 'https://127.0.0.1:'.$this->port.'/a2a',
            default => throw new \InvalidArgumentException('Unknown binding '.$binding),
        };
    }

    public function stop(): void
    {
        $this->process->stop();
    }
}

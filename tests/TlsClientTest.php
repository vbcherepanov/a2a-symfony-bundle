<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests;

use A2A\Bundle\DependencyInjection\A2AExtension;
use A2A\Client\Client;
use A2A\Protocol\{Json, ProtocolException};
use A2A\Bundle\Tests\Fixtures\{TlsCertificates, TlsServer};
use Lf\A2a\V1\{SendMessageRequest, TaskState};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class TlsClientTest extends TestCase
{
    private static TlsCertificates $certificates;
    private ?TlsServer $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$certificates = new TlsCertificates();
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    public static function bindings(): array
    {
        return [['jsonrpc'], ['rest'], ['grpc']];
    }

    private function client(string $binding, bool $withCertificate = true, string $authority = 'ca'): Client
    {
        $certificates = self::$certificates;
        $tls = $binding === 'grpc' ? [
            'root_certificates' => $certificates->pem($authority),
            'certificate_chain' => $withCertificate ? $certificates->pem('client') : null,
            'private_key' => $withCertificate ? file_get_contents($certificates->path('client.key')) : null,
        ] : [
            'ca_file' => $certificates->path($authority.'.pem'),
            'certificate_file' => $withCertificate ? $certificates->path('client.pem') : null,
            'private_key_file' => $withCertificate ? $certificates->path('client.key') : null,
        ];
        $container = new ContainerBuilder();
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->register('executor', Fixtures\TestExecutor::class);
        (new A2AExtension())->load([[
            'executor' => 'executor',
            'card_file' => 'card.json',
            'public_url' => 'https://agent.example',
            'auth' => ['tokens' => ['alice' => 'test-alice-token']],
            'clients' => ['peer' => [
                'binding' => $binding,
                'endpoint' => $this->server->endpoint($binding),
                'timeout_seconds' => 2.0,
                'headers' => ['Authorization' => 'Bearer test-alice-token'],
                'tls' => $tls,
            ]],
        ]], $container);
        $container->compile();
        return $container->get('a2a.client.peer');
    }

    private function request(): SendMessageRequest
    {
        return Json::message('{"message":{"messageId":"'.bin2hex(random_bytes(8)).'","role":"ROLE_USER","parts":[{"text":"hello"}]}}', SendMessageRequest::class);
    }

    #[DataProvider('bindings')]
    public function testNamedClientUsesConfiguredMutualTls(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding, mutual: true);
        $client = $this->client($binding);
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $client->sendMessage($this->request())->getTask()->getStatus()->getState());
        $events = iterator_to_array($client->sendStreamingMessage($this->request()));
        self::assertCount(4, $events);
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $events[3]->getStatusUpdate()->getStatus()->getState());
    }

    #[DataProvider('bindings')]
    public function testNamedClientRejectsUntrustedServer(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding);
        $this->expectHandshakeFailure($this->client($binding, authority: 'untrusted-ca'));
    }

    #[DataProvider('bindings')]
    public function testMutualTlsRejectsMissingClientCertificate(string $binding): void
    {
        $this->server = new TlsServer(self::$certificates, $binding, mutual: true);
        $this->expectHandshakeFailure($this->client($binding, withCertificate: false));
        self::assertSame(TaskState::TASK_STATE_COMPLETED, $this->client($binding)->sendMessage($this->request())->getTask()->getStatus()->getState());
    }

    private function expectHandshakeFailure(Client $client): void
    {
        try {
            $client->sendMessage($this->request());
            self::fail('TLS connection should have been rejected');
        } catch (ProtocolException | TransportExceptionInterface $error) {
            self::assertMatchesRegularExpression('/SSL|TLS|certificate|connection reset|broken pipe|socket closed/i', $error->getMessage());
        }
    }
}

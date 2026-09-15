<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests;

use A2A\Bundle\Client\HttpClientOptions;
use A2A\Bundle\DependencyInjection\A2AExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HttpClientOptionsTest extends TestCase
{
    public static function schemeMismatches(): array
    {
        return [[true, 'http://peer.example'], [false, 'https://peer.example']];
    }

    #[DataProvider('schemeMismatches')]
    public function testEndpointMustMatchTlsSetting(bool $tls, string $endpoint): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('scheme must match');
        (new HttpClientOptions(tls: $tls))->create($endpoint);
    }

    public function testPlainHttpRequiresExplicitOptOut(): void
    {
        self::assertInstanceOf(HttpClientInterface::class, (new HttpClientOptions(tls: false))->create('http://peer.example'));
    }

    public function testMutualTlsRequiresBothCertificateAndKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('requires both');
        new HttpClientOptions(certificateFile: __FILE__);
    }

    public function testCertificateFilesMustBeReadable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('readable files');
        new HttpClientOptions(caFile: __DIR__.'/missing-certificate.pem');
    }

    public function testCertificatesCannotBeUsedWithPlainHttp(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('require TLS');
        new HttpClientOptions(tls: false, caFile: __FILE__);
    }

    public static function certificateFormats(): array
    {
        return [['grpc', 'ca_file'], ['jsonrpc', 'root_certificates'], ['rest', 'certificate_chain']];
    }

    #[DataProvider('certificateFormats')]
    public function testWrongCertificateFormatIsNotSilentlyIgnored(string $binding, string $field): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($binding === 'grpc' ? 'use PEM values' : 'use certificate files');
        (new A2AExtension())->load([[
            'executor' => 'executor',
            'card_file' => 'card.json',
            'public_url' => 'https://agent.example',
            'auth' => ['tokens' => ['alice' => 'test-token']],
            'clients' => ['peer' => ['endpoint' => 'https://peer.example', 'binding' => $binding, 'tls' => [$field => 'certificate']]],
        ]], new ContainerBuilder());
    }
}

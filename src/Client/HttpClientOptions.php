<?php

declare(strict_types=1);

namespace A2A\Bundle\Client;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class HttpClientOptions
{
    public function __construct(
        private bool $tls = true,
        private ?string $caFile = null,
        private ?string $certificateFile = null,
        private ?string $privateKeyFile = null,
    ) {
        if (($certificateFile === null) !== ($privateKeyFile === null)) {
            throw new \InvalidArgumentException('HTTP mTLS requires both certificate_file and private_key_file');
        }
        foreach ([$caFile, $certificateFile, $privateKeyFile] as $file) {
            if ($file !== null && (!$tls || !is_file($file) || !is_readable($file))) {
                throw new \InvalidArgumentException('HTTP certificates require TLS and readable files');
            }
        }
    }

    public function create(string $endpoint): HttpClientInterface
    {
        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        if ($scheme !== ($this->tls ? 'https' : 'http')) {
            throw new \InvalidArgumentException('HTTP endpoint scheme must match tls.enabled');
        }
        return HttpClient::create([
            'cafile' => $this->caFile,
            'local_cert' => $this->certificateFile,
            'local_pk' => $this->privateKeyFile,
            'verify_peer' => true,
            'verify_host' => true,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests\Fixtures;

use Symfony\Component\Process\Process;

final readonly class TlsCertificates
{
    public string $directory;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir().'/a2a-tls-'.bin2hex(random_bytes(8));
        if (!mkdir($this->directory, 0700)) {
            throw new \RuntimeException('Cannot create certificate directory');
        }
        foreach (['ca', 'untrusted-ca'] as $name) {
            (new Process(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-noenc', '-days', '1', '-subj', '/CN='.$name, '-addext', 'basicConstraints=critical,CA:TRUE', '-keyout', $this->path($name.'.key'), '-out', $this->path($name.'.pem')]))->mustRun();
        }
        $this->issue('server', 'ca', 'subjectAltName=DNS:localhost,IP:127.0.0.1', 'serverAuth');
        $this->issue('wrong-host', 'ca', 'subjectAltName=DNS:wrong.example', 'serverAuth');
        $this->issue('client', 'ca', 'subjectAltName=DNS:client.example', 'clientAuth');
        $this->issue('untrusted-client', 'untrusted-ca', 'subjectAltName=DNS:client.example', 'clientAuth');
    }

    public function path(string $name): string
    {
        return $this->directory.'/'.$name;
    }

    public function pem(string $name): string
    {
        $contents = file_get_contents($this->path($name.'.pem'));
        if ($contents === false) {
            throw new \RuntimeException('Cannot read test certificate '.$name);
        }
        return $contents;
    }

    private function issue(string $name, string $authority, string $subject, string $purpose): void
    {
        (new Process(['openssl', 'req', '-new', '-newkey', 'rsa:2048', '-noenc', '-subj', '/CN='.$name, '-keyout', $this->path($name.'.key'), '-out', $this->path($name.'.csr')]))->mustRun();
        $extensions = "basicConstraints=critical,CA:FALSE\nkeyUsage=critical,digitalSignature,keyEncipherment\nextendedKeyUsage=".$purpose."\n".$subject."\n";
        if (file_put_contents($this->path($name.'.ext'), $extensions) === false) {
            throw new \RuntimeException('Cannot write test certificate extensions');
        }
        (new Process(['openssl', 'x509', '-req', '-in', $this->path($name.'.csr'), '-CA', $this->path($authority.'.pem'), '-CAkey', $this->path($authority.'.key'), '-CAcreateserial', '-days', '1', '-extfile', $this->path($name.'.ext'), '-out', $this->path($name.'.pem')]))->mustRun();
    }
}

<?php

declare(strict_types=1);

namespace A2A\Bundle;

use A2A\Protocol\{Json, Validator};
use A2A\Transport\Grpc\GrpcServerOptions;
use A2A\Transport\Http\HttpOptions;
use Lf\A2a\V1\{AgentCard, AgentInterface};

final readonly class CardLoader
{
    public function __construct(private HttpOptions $http, private GrpcServerOptions $grpc, private string $publicUrl, private ?string $grpcPublicUrl)
    {
    }
    public function load(string $path): AgentCard
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('A2A card_file is not readable: '.$path);
        }
        $data = file_get_contents($path);
        if ($data === false) {
            throw new \RuntimeException('Cannot read agent card');
        }
        $card = Json::message($data, AgentCard::class);
        if (count($card->getSignatures()) > 0) {
            throw new \InvalidArgumentException('Signed cards require a custom AgentCard service because configured interfaces change the signed payload');
        }
        $interfaces = [];
        foreach ([['JSONRPC', $this->http->jsonRpcEnabled, $this->publicUrl.$this->http->rpcPath], ['HTTP+JSON', $this->http->restEnabled, $this->publicUrl.$this->http->restPath], ['GRPC', $this->grpc->enabled, $this->grpcPublicUrl]] as [$binding, $enabled, $url]) {
            if ($enabled) {
                if (!is_string($url) || $url === '') {
                    throw new \InvalidArgumentException('Missing public URL for '.$binding);
                }
                $interfaces[] = (new AgentInterface())->setProtocolBinding($binding)->setProtocolVersion('1.0')->setUrl($url);
            }
        }
        $card->setSupportedInterfaces($interfaces);
        (new Validator())->validate($card);
        return $card;
    }
}

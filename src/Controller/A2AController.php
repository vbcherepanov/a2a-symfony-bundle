<?php

declare(strict_types=1);

namespace A2A\Bundle\Controller;

use A2A\Transport\Http\{Endpoint, Request as ProtocolRequest};
use Symfony\Component\HttpFoundation\{Request, Response, StreamedResponse};

final readonly class A2AController
{
    public function __construct(private Endpoint $endpoint)
    {
    }
    public function __invoke(Request $request): Response
    {
        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[$name] = implode(',', $values);
        }
        $result = $this->endpoint->handle(new ProtocolRequest($request->getMethod(), $request->getRequestUri(), $request->getContent(), $headers));
        return $this->response($result);
    }
    private function response(\A2A\Transport\Http\Response $result): Response
    {
        if (is_string($result->body)) {
            return new Response($result->body, $result->status, $result->headers);
        }
        return new StreamedResponse(static function () use ($result): void {
            foreach ($result->body as $chunk) {
                echo $chunk;
                flush();
            }
        }, $result->status, $result->headers);
    }
}

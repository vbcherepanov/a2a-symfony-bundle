<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests\Fixtures;

use A2A\Observability\PrometheusMetrics;
use A2A\Protocol\{Json, Validator};
use A2A\Security\BearerAuthenticator;
use A2A\Server\{Dispatcher, Gateway, Processor, PushManager, PushOptions, ServerOptions, TaskService};
use A2A\Storage\FileTaskRepository;
use A2A\Transport\Grpc\GrpcEndpoint;
use A2A\Transport\Http\{Endpoint, HttpOptions};
use Lf\A2a\V1\AgentCard;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\HttpClient;

final readonly class Application
{
    public FileTaskRepository $repository;
    public TaskService $service;
    public Processor $processor;
    public Endpoint $endpoint;
    public GrpcEndpoint $grpc;
    public PrometheusMetrics $metrics;
    public AgentCard $card;
    public PushManager $push;
    public ServerOptions $options;
    public function __construct(string $directory, ?\A2A\Server\Executor $executor = null, ?\A2A\Security\Authenticator $authenticator = null, ?AgentCard $card = null, ?PushOptions $pushOptions = null, ?HttpOptions $httpOptions = null)
    {
        $this->repository = new FileTaskRepository($directory);
        $this->card = $card ?? Json::message(file_get_contents(__DIR__.'/card.json'), AgentCard::class);
        $this->options = new ServerOptions(requestTimeoutSeconds: 5.0, pollIntervalSeconds: 0.01);
        $logger = new NullLogger();
        $this->push = new PushManager(HttpClient::create(), $pushOptions ?? new PushOptions(['example.com']), $this->repository, $logger);
        $this->processor = new Processor($this->repository, $executor ?? new RecordingExecutor(), $this->push, $logger, $this->options);
        $this->service = new TaskService($this->repository, $this->processor, $this->push, $this->card, $this->options, $this->card);
        $this->metrics = new PrometheusMetrics();
        $gateway = new Gateway(new Dispatcher($this->service, new Validator()), $authenticator ?? new BearerAuthenticator(['alice' => 'test-alice-token', 'bob' => 'test-bob-token']), $this->metrics, $logger, $this->card, $this->options);
        $this->endpoint = new Endpoint($gateway, $this->card, $httpOptions ?? new HttpOptions(), $this->options);
        $this->grpc = new GrpcEndpoint($gateway, $this->options);
    }
}

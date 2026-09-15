<?php

declare(strict_types=1);

namespace A2A\Bundle\DependencyInjection;

use A2A\Bundle\{CardLoader, Controller\A2AController, Routing\A2ARouteLoader, Security\AccessTokenAuthenticator, Storage\DoctrineTaskRepository};
use A2A\Bundle\Command\{GrpcServeCommand, StorageInitCommand, WorkCommand};
use A2A\Bundle\Client\HttpClientOptions;
use A2A\Client\{CallOptions, Client};
use A2A\Bundle\Messenger\{ProcessQueueHandler, ScheduledAgentService};
use A2A\Observability\PrometheusMetrics;
use A2A\Protocol\Validator;
use A2A\Security\{Authenticator, BearerAuthenticator};
use A2A\Server\{AgentService, Dispatcher, Gateway, Processor, PushManager, PushOptions, ServerOptions, TaskService};
use A2A\Storage\{FileTaskRepository, TaskRepository};
use A2A\Transport\Grpc\{GrpcClientOptions, GrpcEndpoint, GrpcServerOptions, GrpcTransport, OpenSwooleRuntime};
use A2A\Transport\Http\{Endpoint, HttpOptions};
use A2A\Transport\HttpTransport;
use Lf\A2a\V1\AgentCard;
use Monolog\{Logger, Formatter\JsonFormatter, Handler\StreamHandler};
use Symfony\Component\DependencyInjection\{ContainerBuilder, Definition, Reference};
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class A2AExtension extends Extension
{
    public function getAlias(): string
    {
        return 'a2a';
    }
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $ref = static fn (string $id): Reference => new Reference($id);
        $http = $config['transports'];
        $grpc = $http['grpc'];
        if (!$grpc['enabled'] && !$http['jsonrpc']['enabled'] && !$http['rest']['enabled']) {
            throw new \InvalidArgumentException('At least one A2A server transport must be enabled');
        }
        if ($grpc['enabled'] && !$grpc['public_url']) {
            throw new \InvalidArgumentException('a2a.transports.grpc.public_url is required when gRPC is enabled');
        }
        $container->register(HttpOptions::class)->setArguments([$http['jsonrpc']['enabled'], $http['rest']['enabled'], $http['jsonrpc']['path'], $http['rest']['path'], $config['discovery']['path'], $config['discovery']['cache_seconds'], $config['discovery']['last_modified']]);
        $container->register(GrpcServerOptions::class)->setArguments([$grpc['enabled'], $grpc['host'], $grpc['port'], $grpc['workers'], $grpc['max_requests_per_worker'], $grpc['tls']['certificate_file'], $grpc['tls']['private_key_file'], $grpc['tls']['client_ca_file'], $grpc['tls']['require_client_certificate'], $grpc['max_concurrent_streams'], $grpc['max_connections']]);
        $limits = $config['limits'];
        $container->register(ServerOptions::class)->setArguments([$limits['request_timeout_seconds'], $limits['poll_interval_seconds'], $limits['worker_lease_seconds'], $limits['max_message_bytes'], $limits['max_events_per_task'], $limits['max_history_messages'], $limits['max_push_configs_per_task']]);
        $push = $config['push'];
        $container->register(PushOptions::class)->setArguments([$push['allowed_hosts'], $push['timeout_seconds'], $push['max_attempts'], $push['retry_delay_seconds'], $push['max_pending_per_task'], $push['allow_private_network'], $push['require_https']]);
        $container->register(CardLoader::class)->setArguments([$ref(HttpOptions::class), $ref(GrpcServerOptions::class), $config['public_url'], $grpc['public_url']]);
        $container->register(AgentCard::class)->setFactory([$ref(CardLoader::class), 'load'])->setArguments([$config['card_file']]);
        if ($config['extended_card_file'] !== null) {
            $container->register('a2a.extended_card', AgentCard::class)->setFactory([$ref(CardLoader::class), 'load'])->setArguments([$config['extended_card_file']]);
        }
        $auth = $config['auth'];
        if ($auth['service']) {
            $container->setAlias(Authenticator::class, $auth['service']);
        } elseif ($auth['access_token_handler']) {
            $container->register(AccessTokenAuthenticator::class)->setArguments([$ref($auth['access_token_handler'])]);
            $container->setAlias(Authenticator::class, AccessTokenAuthenticator::class);
        } else {
            if ($auth['tokens'] === []) {
                throw new \InvalidArgumentException('Configure a2a.auth.tokens, access_token_handler, or service');
            }
            $container->register(BearerAuthenticator::class)->setArguments([$auth['tokens']]);
            $container->setAlias(Authenticator::class, BearerAuthenticator::class);
        }
        $storage = $config['storage'];
        if ($storage['driver'] === 'doctrine') {
            $container->register(DoctrineTaskRepository::class)->setArguments([$ref($storage['connection']), $storage['table'], $storage['max_update_attempts']]);
            $container->setAlias(TaskRepository::class, DoctrineTaskRepository::class);
        } else {
            $container->register(FileTaskRepository::class)->setArguments([$storage['directory']]);
            $container->setAlias(TaskRepository::class, FileTaskRepository::class);
        }
        $formatter = new Definition(JsonFormatter::class);
        $handler = (new Definition(StreamHandler::class, [$config['log_stream']]))->addMethodCall('setFormatter', [$formatter]);
        $container->register('a2a.logger', Logger::class)->setArguments(['a2a', [$handler]]);
        $container->register('a2a.metrics', PrometheusMetrics::class)->setArgument('$stateFile', $config['metrics_file'])->setPublic(true);
        $container->register('a2a.http_client', HttpClientInterface::class)->setFactory([HttpClient::class, 'create']);
        foreach ($config['clients'] as $name => $client) {
            $options = new Definition(CallOptions::class, [
                $client['timeout_seconds'],
                $client['max_message_bytes'],
                $client['headers'],
                $client['extensions'],
            ]);
            $tls = $client['tls'];
            if ($client['binding'] === 'grpc') {
                if ($tls['ca_file'] !== null || $tls['certificate_file'] !== null || $tls['private_key_file'] !== null) {
                    throw new \InvalidArgumentException('gRPC clients use PEM values: root_certificates, certificate_chain and private_key');
                }
                $grpcOptions = new Definition(GrpcClientOptions::class, [
                    $tls['enabled'],
                    $tls['root_certificates'],
                    $tls['private_key'],
                    $tls['certificate_chain'],
                    $client['max_message_bytes'],
                    $client['max_message_bytes'],
                ]);
                $transport = (new Definition(GrpcTransport::class))
                    ->setFactory([$grpcOptions, 'connect'])
                    ->setArguments([$client['endpoint']]);
            } else {
                if ($tls['root_certificates'] !== null || $tls['certificate_chain'] !== null || $tls['private_key'] !== null) {
                    throw new \InvalidArgumentException('HTTP clients use certificate files: ca_file, certificate_file and private_key_file');
                }
                $httpOptions = new Definition(HttpClientOptions::class, [
                    $tls['enabled'],
                    $tls['ca_file'],
                    $tls['certificate_file'],
                    $tls['private_key_file'],
                ]);
                $httpClient = (new Definition(HttpClientInterface::class))
                    ->setFactory([$httpOptions, 'create'])
                    ->setArguments([$client['endpoint']]);
                $transport = new Definition(HttpTransport::class, [$httpClient, $client['endpoint'], $client['binding'] === 'jsonrpc']);
            }
            $container->register('a2a.client.'.$name, Client::class)->setArguments([$transport, $options])->setPublic(true);
            $container->registerAliasForArgument('a2a.client.'.$name, Client::class, $name.'Client');
        }
        $container->register(Validator::class);
        $container->register(PushManager::class)->setArguments([$ref('a2a.http_client'), $ref(PushOptions::class), $ref(TaskRepository::class), $ref('a2a.logger')]);
        $container->register(Processor::class)->setArguments([$ref(TaskRepository::class), $ref($config['executor']), $ref(PushManager::class), $ref('a2a.logger'), $ref(ServerOptions::class)]);
        $container->register(TaskService::class)->setArguments([$ref(TaskRepository::class), $ref(Processor::class), $ref(PushManager::class), $ref(AgentCard::class), $ref(ServerOptions::class), $config['extended_card_file'] !== null ? $ref('a2a.extended_card') : null]);
        $container->setAlias(AgentService::class, TaskService::class);
        if ($config['messenger']['enabled']) {
            $container->register(ScheduledAgentService::class)->setArguments([$ref(TaskService::class), $ref($config['messenger']['bus']), $ref('messenger.senders_locator')]);
            $container->setAlias(AgentService::class, ScheduledAgentService::class);
            $container->register(ProcessQueueHandler::class)->setArguments([$ref(Processor::class)])->addTag('messenger.message_handler');
        }
        $container->register(Dispatcher::class)->setArguments([$ref(AgentService::class), $ref(Validator::class)]);
        $container->register(Gateway::class)->setArguments([$ref(Dispatcher::class), $ref(Authenticator::class), $ref($config['metrics_service']), $ref('a2a.logger'), $ref(AgentCard::class), $ref(ServerOptions::class)]);
        $container->register(Endpoint::class)->setArguments([$ref(Gateway::class), $ref(AgentCard::class), $ref(HttpOptions::class), $ref(ServerOptions::class)]);
        $container->register(A2AController::class)->setArguments([$ref(Endpoint::class)])->addTag('controller.service_arguments');
        $container->register(A2ARouteLoader::class)->setArguments([$ref(HttpOptions::class)])->addTag('routing.loader');
        $container->register(GrpcEndpoint::class)->setArguments([$ref(Gateway::class), $ref(ServerOptions::class)]);
        $container->register(OpenSwooleRuntime::class)->setArguments([$ref(GrpcEndpoint::class), $ref(GrpcServerOptions::class), $ref(ServerOptions::class), $ref('a2a.logger')]);
        $container->register(GrpcServeCommand::class)->setArguments([$ref(OpenSwooleRuntime::class)])->addTag('console.command');
        $container->register(WorkCommand::class)->setArguments([$ref(Processor::class), $ref(ServerOptions::class)])->addTag('console.command');
        $container->register(StorageInitCommand::class)->setArguments([$ref(TaskRepository::class)])->addTag('console.command');
    }
}

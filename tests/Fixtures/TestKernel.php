<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests\Fixtures;

use A2A\Bundle\A2ABundle;
use A2A\Bundle\Messenger\ProcessQueue;
use A2A\Transport\Grpc\GrpcServerOptions;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

final class TestKernel extends Kernel
{
    use MicroKernelTrait;
    public function __construct(private readonly bool $grpc = false, private readonly bool $messenger = false, private readonly bool $doctrine = false)
    {
        parent::__construct('test', true);
        $this->runDirectory = sys_get_temp_dir().'/a2a-bundle-'.bin2hex(random_bytes(8));
    }
    private string $runDirectory;
    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new A2ABundle();
    }
    public function getCacheDir(): string
    {
        return $this->runDirectory.'/cache';
    }
    public function getLogDir(): string
    {
        return $this->runDirectory.'/log';
    }
    protected function configureContainer(ContainerConfigurator $container): void
    {
        $framework = ['secret' => 'test-secret', 'test' => true, 'http_method_override' => false, 'router' => ['utf8' => true]];
        if ($this->messenger) {
            $framework['messenger'] = ['transports' => ['async' => 'in-memory://'], 'routing' => [ProcessQueue::class => 'async']];
        }
        $container->extension('framework', $framework);
        $container->services()->set(TestExecutor::class);
        if ($this->doctrine) {
            $container->services()->set('test.connection', \Doctrine\DBAL\Connection::class)->factory([\Doctrine\DBAL\DriverManager::class, 'getConnection'])->args([['driver' => 'pdo_sqlite', 'memory' => true]]);
        }
        $container->extension('a2a', [
            'executor' => TestExecutor::class,
            'card_file' => __DIR__.'/card.json',
            'public_url' => 'https://agent.example',
            'metrics_file' => $this->runDirectory.'/metrics.json',
            'clients' => ['peer' => ['endpoint' => 'https://peer.example/a2a/rpc']],
            'auth' => ['tokens' => ['alice' => 'test-token']],
            'storage' => ['driver' => $this->doctrine ? 'doctrine' : 'file', 'directory' => $this->runDirectory.'/storage', 'connection' => 'test.connection'],
            'messenger' => ['enabled' => $this->messenger],
            'transports' => ['grpc' => ['enabled' => $this->grpc, 'public_url' => $this->grpc ? '127.0.0.1:50051' : null]],
        ]);
        $container->services()->alias('test.grpc_options', GrpcServerOptions::class)->public();
        $container->services()->alias('test.repository', \A2A\Storage\TaskRepository::class)->public();
    }
    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('.', 'a2a');
    }
}

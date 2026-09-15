<?php

declare(strict_types=1);

namespace A2A\Bundle\Tests;

use A2A\Bundle\DependencyInjection\A2AExtension;
use A2A\Bundle\Routing\A2ARouteLoader;
use A2A\Bundle\Tests\Fixtures\TestKernel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

final class UnconfiguredBundleTest extends TestCase
{
    public function testKernelBootsWithoutA2aConfiguration(): void
    {
        $kernel = new TestKernel(configured: false);
        try {
            $kernel->boot();
            $container = $kernel->getContainer();
            self::assertFalse($container->has('a2a.metrics'));
            self::assertFalse($container->has('a2a.client.peer'));
            foreach (array_keys($container->get('router')->getRouteCollection()->all()) as $name) {
                self::assertStringStartsNotWith('a2a_', $name);
            }
        } finally {
            $kernel->shutdown();
        }
    }

    public function testDebugRouterAcceptsRouteImportWithoutConfiguration(): void
    {
        $kernel = new TestKernel(configured: false, routeFile: true);
        try {
            $application = new Application($kernel);
            $command = new CommandTester($application->find('debug:router'));
            self::assertSame(0, $command->execute(['--format' => 'json']));
            self::assertSame([], json_decode($command->getDisplay(), true, flags: JSON_THROW_ON_ERROR));
        } finally {
            $kernel->shutdown();
        }
    }

    public function testEmptyConfigurationRegistersOnlyAnInactiveRouteLoader(): void
    {
        $container = new ContainerBuilder();
        $before = array_keys($container->getDefinitions());
        (new A2AExtension())->load([[]], $container);
        self::assertSame([...$before, A2ARouteLoader::class], array_keys($container->getDefinitions()));
        self::assertSame([], $container->getAliases());
        self::assertCount(0, (new A2ARouteLoader())->load('.', 'a2a'));
    }

    public static function requiredFields(): iterable
    {
        yield ['card_file'];
        yield ['executor'];
        yield ['public_url'];
    }

    #[DataProvider('requiredFields')]
    public function testPartialConfigurationStillRequiresServerSettings(string $field): void
    {
        $configuration = [
            'card_file' => 'card.json',
            'executor' => 'executor',
            'public_url' => 'https://agent.example',
        ];
        unset($configuration[$field]);
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($field);
        (new A2AExtension())->load([$configuration], new ContainerBuilder());
    }
}

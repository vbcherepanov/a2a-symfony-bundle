<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$directory = sys_get_temp_dir().'/a2a-flex-'.bin2hex(random_bytes(8));
$filesystem = new Filesystem();
$filesystem->mkdir($directory);

function runFlexCommand(array $command, string $directory): string
{
    $process = new Process($command, $directory, timeout: 600);
    $process->mustRun(static function (string $type, string $buffer): void {
        fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
    });

    return $process->getOutput();
}

runFlexCommand(['composer', 'archive', '--format=zip', '--dir='.$directory, '--file=bundle'], $root);
runFlexCommand(['unzip', '-q', $directory.'/bundle.zip', '-d', $directory.'/package'], $root);
runFlexCommand(['composer', 'create-project', 'symfony/skeleton:8.0.*', 'application', '--no-interaction', '--prefer-dist', '--no-progress'], $directory);

$application = $directory.'/application';
$configurationFile = $application.'/composer.json';
$configuration = json_decode(file_get_contents($configurationFile), flags: JSON_THROW_ON_ERROR);
$configuration->repositories = [[
    'type' => 'path',
    'url' => $directory.'/package',
    'options' => ['symlink' => false, 'versions' => ['vbcherepanov/a2a-symfony-bundle' => '1.0.1']],
]];
$filesystem->dumpFile($configurationFile, json_encode($configuration, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

runFlexCommand(['composer', 'require', 'vbcherepanov/a2a-symfony-bundle:1.0.1', '--no-interaction', '--prefer-dist', '--no-progress'], $application);
$bundles = require $application.'/config/bundles.php';
if (($bundles[A2A\Bundle\A2ABundle::class]['all'] ?? false) !== true) {
    throw new RuntimeException('Flex did not automatically register A2ABundle');
}
foreach (['dev', 'prod'] as $environment) {
    runFlexCommand(['php', 'bin/console', 'cache:clear', '--env='.$environment, '--no-interaction'], $application);
    $output = runFlexCommand(['php', 'bin/console', 'debug:router', '--format=json', '--env='.$environment], $application);
    foreach (array_keys(json_decode($output, true, flags: JSON_THROW_ON_ERROR)) as $name) {
        if (str_starts_with($name, 'a2a_')) {
            throw new RuntimeException('Unconfigured bundle exposed route '.$name);
        }
    }
}
fwrite(STDOUT, "Flex registered the bundle; Composer scripts and dev/prod cache clearing passed without A2A configuration.\n");

<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

require dirname(__DIR__).'/vendor/autoload.php';

$root = dirname(__DIR__);
$filesystem = new Filesystem();
$configuration = json_decode(file_get_contents($root.'/composer.json'), true, flags: JSON_THROW_ON_ERROR);
$lock = json_decode(file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);

foreach (['7.4', '8.0'] as $version) {
    $directory = sys_get_temp_dir().'/a2a-symfony-'.$version.'-'.bin2hex(random_bytes(8));
    $filesystem->mkdir($directory);
    foreach (['src', 'tests'] as $path) {
        $filesystem->mirror($root.'/'.$path, $directory.'/'.$path);
    }
    $filesystem->copy($root.'/phpunit.xml', $directory.'/phpunit.xml');
    $consumer = $configuration;
    foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
        if (str_starts_with($package['name'], 'symfony/') && preg_match('/^v?[78]\./', $package['version'])) {
            $section = isset($consumer['require'][$package['name']]) ? 'require' : 'require-dev';
            $consumer[$section][$package['name']] = $version.'.*';
        }
    }
    $filesystem->dumpFile($directory.'/composer.json', json_encode($consumer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    foreach ([['composer', 'update', '--no-interaction', '--prefer-dist', '--no-progress'], ['composer', 'test'], ['composer', 'build']] as $command) {
        fwrite(STDOUT, 'Symfony '.$version.': '.implode(' ', $command)."\n");
        $process = new Process($command, $directory, timeout: 600);
        $process->mustRun(static function (string $type, string $buffer): void {
            fwrite($type === Process::ERR ? STDERR : STDOUT, $buffer);
        });
    }
}

<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$temporary = sys_get_temp_dir().'/a2a-package-'.bin2hex(random_bytes(8));
if (!mkdir($temporary, 0700)) {
    throw new RuntimeException('Cannot create package test directory');
}

function runCommand(array $command, string $directory): void
{
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $directory);
    if (!is_resource($process) || proc_close($process) !== 0) {
        throw new RuntimeException('Package check command failed: '.implode(' ', $command));
    }
}

runCommand(['composer', 'archive', '--format=zip', '--dir=dist', '--file=a2a-symfony-bundle'], $root);
$source = $temporary.'/package';
runCommand(['unzip', '-q', $root.'/dist/a2a-symfony-bundle.zip', '-d', $source], $root);
$allowed = ['composer.json', 'README.md', 'LICENSE', 'CHANGELOG.md', 'src', 'docs'];
foreach (new DirectoryIterator($source) as $entry) {
    if (!$entry->isDot() && !in_array($entry->getFilename(), $allowed, true)) {
        throw new RuntimeException('Unexpected file in package: '.$entry->getFilename());
    }
}
foreach (['composer.json', 'README.md', 'LICENSE', 'src/A2ABundle.php', 'docs/configuration.yaml'] as $file) {
    if (!is_file($source.'/'.$file)) {
        throw new RuntimeException('Package is missing '.$file);
    }
}
$consumer = [
    'name' => 'a2a/package-check',
    'require' => ['vbcherepanov/a2a-symfony-bundle' => '1.0.0'],
    'repositories' => [[
        'type' => 'path',
        'url' => $source,
        'options' => ['symlink' => false, 'versions' => ['vbcherepanov/a2a-symfony-bundle' => '1.0.0']],
    ]],
    'config' => ['allow-plugins' => false],
];
if (file_put_contents($temporary.'/composer.json', json_encode($consumer, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)) === false) {
    throw new RuntimeException('Cannot write package consumer configuration');
}
runCommand(['composer', 'install', '--no-dev', '--no-interaction', '--prefer-dist', '--no-progress'], $temporary);
runCommand([PHP_BINARY, '-n', '-d', 'extension=bcmath', $root.'/tools/package-smoke.php', $temporary.'/vendor/autoload.php'], $temporary);
if (file_put_contents($root.'/dist/SHA256SUMS', hash_file('sha256', $root.'/dist/a2a-symfony-bundle.zip')."  a2a-symfony-bundle.zip\n") === false) {
    throw new RuntimeException('Cannot write package checksum');
}
fwrite(STDOUT, "Package contents and clean HTTP-only installation verified.\n");

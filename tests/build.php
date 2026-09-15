<?php

declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$kernel = new A2A\Bundle\Tests\Fixtures\TestKernel();
$kernel->boot();
$kernel->shutdown();
fwrite(STDOUT, "Symfony container compiled successfully.\n");

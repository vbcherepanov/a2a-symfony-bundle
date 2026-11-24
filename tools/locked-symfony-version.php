<?php

declare(strict_types=1);

/**
 * Returns the Symfony minor version (for example "8.1") locked in composer.lock.
 *
 * End-of-life Symfony minors keep unfixed security advisories and Composer refuses
 * to install them, so the compatibility checks follow the locked minor instead of
 * a fixed one.
 */
function lockedSymfonyVersion(string $root): string
{
    $lock = json_decode(file_get_contents($root.'/composer.lock'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($lock['packages'] as $package) {
        if ('symfony/framework-bundle' === $package['name'] && 1 === preg_match('/^v?(\d+\.\d+)\./', $package['version'], $matches)) {
            return $matches[1];
        }
    }

    throw new RuntimeException('composer.lock does not lock symfony/framework-bundle to a release version');
}

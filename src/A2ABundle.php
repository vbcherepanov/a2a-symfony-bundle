<?php

declare(strict_types=1);

namespace A2A\Bundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class A2ABundle extends Bundle
{
    public function getContainerExtension(): \Symfony\Component\DependencyInjection\Extension\ExtensionInterface
    {
        return $this->extension = $this->extension ?: new DependencyInjection\A2AExtension();
    }
}

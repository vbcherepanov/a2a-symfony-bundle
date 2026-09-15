<?php

declare(strict_types=1);

namespace A2A\Bundle\Routing;

use A2A\Bundle\Controller\A2AController;
use A2A\Transport\Http\HttpOptions;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\{Route, RouteCollection};

final class A2ARouteLoader extends Loader
{
    public function __construct(private readonly HttpOptions $options)
    {
        parent::__construct();
    }
    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        $controller = ['_controller' => A2AController::class];
        $routes->add('a2a_card', new Route($this->options->cardPath, $controller, methods: ['GET']));
        if ($this->options->jsonRpcEnabled) {
            $routes->add('a2a_rpc', new Route($this->options->rpcPath, $controller, methods: ['POST']));
        }
        if ($this->options->restEnabled) {
            $routes->add('a2a_rest', new Route(rtrim($this->options->restPath, '/').'/{path}', $controller, ['path' => '.+'], methods: ['GET', 'POST', 'DELETE']));
        }
        return $routes;
    }
    public function supports(mixed $resource, ?string $type = null): bool
    {
        return $type === 'a2a';
    }
}

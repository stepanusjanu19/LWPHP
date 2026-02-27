<?php

namespace Kei\Lwphp\Routing;

use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Kei\Lwphp\Core\ConfigLoader;

use function FastRoute\simpleDispatcher;

/**
 * Router
 *
 * Builds and returns a FastRoute Dispatcher from the route definitions
 * declared in Routes::define(). Meant to be registered in the DI container
 * so App::run() can retrieve it via type-hinting.
 */
class Router
{
    private Dispatcher $dispatcher;

    public function __construct(private readonly ConfigLoader $config)
    {
        $this->dispatcher = $this->buildDispatcher();
    }

    private function buildDispatcher(): Dispatcher
    {
        return simpleDispatcher(function (RouteCollector $r) {
            Routes::define($r);
        });
    }

    /**
     * Dispatch the incoming method + URI and return FastRoute's route info array.
     *
     * [0] => Dispatcher::NOT_FOUND | METHOD_NOT_ALLOWED | FOUND
     * [1] => handler (for FOUND) or allowed methods (for METHOD_NOT_ALLOWED)
     * [2] => route variables (for FOUND)
     */
    public function dispatch(string $method, string $uri): array
    {
        // Strip query string if present
        $pos = strpos($uri, '?');
        if ($pos !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $uri = rawurldecode($uri);

        return $this->dispatcher->dispatch($method, $uri);
    }

    /**
     * Expose the raw FastRoute Dispatcher for direct use.
     */
    public function getDispatcher(): Dispatcher
    {
        return $this->dispatcher;
    }
}

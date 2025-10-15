<?php

namespace Kei\Lwphp\Core;

use DI\ContainerBuilder;
use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

//load path framework
use Kei\Lwphp\Middleware\MiddlewareStack;

class App {
    private $container;

    public function __construct()
    {
        //configuration builder for container DI
        $builder = new ContainerBuilder();
        $builder->addDefinitions(__DIR__ . '/../../config/app.php');
        $builder->addDefinitions(__DIR__ . '/../../config/database.php');

        //example : register cache (plug & play) can swap adapter
        $this->container->set(\Symfony\Contracts\Cache\CacheInterface::class, new FilesystemAdapter());
    }

    public function run(ServerRequestInterface $request): void
    {
        $dispatcher = $this->container->get(Dispatcher::class);
        $routeInfo = $dispatcher->dispatch($request->getMethod(), $request->getUri()->getPath());

        switch ($routeInfo[0]){
            case Dispatcher::NOT_FOUND:
                $response = $this->createResponse(404, 'Not Found');
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $response = $this->createResponse(405, 'Method Not Allowed');
                break;
            case Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars = $routeInfo[2];

                if(is_array($handler) && class_exists($handler[0])){
                    $controller = $this->container->get($handler[0]);
                    $handler = [$controller, $handler[1]];
                }

                //plug and play for middleware
                $middlewareStack = new MiddlewareStack();
//                $middlewareStack->add();

                //handle request with middleware
                $finalHandler = function($req) use ($handler, $vars) {
                    return call_user_func($handler, $req, $vars);
                };

                $response = $middlewareStack->handle($request, $finalHandler);
                break;
        }
        $this->emitResponse($response);
    }

    private function createResponse(int $status, string $body): ResponseInterface
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        return $factory->createResponse($status)->withBody($factory->createStream($body));
    }

    private function emitResponse(ResponseInterface $response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values){
            foreach ($values as $value){
                header("$name: $value", false);
            }
        }
        echo $response->getBody();
    }
}

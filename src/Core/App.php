<?php

namespace Kei\Lwphp\Core;

use DI\ContainerBuilder;
use FastRoute\Dispatcher;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Kei\Lwphp\Middleware\MiddlewareStack;

class App
{
    private \DI\Container $container;
    private ConfigLoader $config;

    public static float $bootTime = 0.0;

    public function __construct()
    {
        if (self::$bootTime === 0.0) {
            self::$bootTime = hrtime(true) / 1e6;
        }

        $this->config = new ConfigLoader();

        $builder = new ContainerBuilder();
        $builder->useAttributes(true);

        $definitionsFile = __DIR__ . '/../../config/di/definitions.php';
        if (file_exists($definitionsFile)) {
            $builder->addDefinitions($definitionsFile);
        }

        // PHP-DI compilation in non-debug mode for ~3× faster container resolution
        $debug = (bool) $this->config->get('app.debug', true);
        if (!$debug) {
            $compilationDir = __DIR__ . '/../../storage/cache/di';
            if (!is_dir($compilationDir)) {
                @mkdir($compilationDir, 0755, true);
            }
            $builder->enableCompilation($compilationDir);
            $builder->writeProxiesToFile(true, $compilationDir . '/proxies');
        }

        $this->container = $builder->build();
        $this->container->set(ConfigLoader::class, $this->config);

        // Auto-create / update DB schema on boot (dev mode only)
        if ($debug) {
            $this->bootSchema();
        }
    }

    // -------------------------------------------------------------------------
    // Schema auto-boot
    // -------------------------------------------------------------------------

    private function bootSchema(): void
    {
        try {
            $em = $this->container->get(\Doctrine\ORM\EntityManagerInterface::class);
            $schema = new SchemaManager($em);
            $schema->updateSchema();
        } catch (\Throwable $e) {
            // Don't crash web requests if DB is misconfigured — just log silently
            error_log('[LWPHP SchemaManager] ' . $e->getMessage());
        }
    }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getConfig(): ConfigLoader
    {
        return $this->config;
    }
    public function getContainer(): \DI\Container
    {
        return $this->container;
    }

    // -------------------------------------------------------------------------
    // HTTP Kernel
    // -------------------------------------------------------------------------

    public function run(ServerRequestInterface $request): void
    {
        ob_start();

        // ── Pre-route CORS preflight ─────────────────────────────────────────
        // Handle OPTIONS before FastRoute dispatch so unknown paths still get
        // a proper 204 instead of a 404 for browser DELETE/PUT/POST preflights.
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
            $response = $factory->createResponse(204)
                ->withHeader('Access-Control-Allow-Origin', '*')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
                ->withHeader('Access-Control-Max-Age', '86400')
                ->withHeader('Content-Length', '0');
            $this->emitResponse($response);
            ob_end_flush();
            return;
        }

        try {
            /** @var Dispatcher $dispatcher */
            $dispatcher = $this->container->get(Dispatcher::class);

            $routeInfo = $dispatcher->dispatch(
                $request->getMethod(),
                $request->getUri()->getPath()
            );

            $response = match ($routeInfo[0]) {
                Dispatcher::NOT_FOUND => $this->createJson(404, [
                    'error' => 'Not Found',
                    'path' => $request->getUri()->getPath(),
                ]),
                Dispatcher::METHOD_NOT_ALLOWED => $this->createJson(405, [
                    'error' => 'Method Not Allowed',
                    'allowed' => $routeInfo[1],
                ])->withHeader('Allow', implode(', ', $routeInfo[1])),
                Dispatcher::FOUND => $this->handleFoundRoute($request, $routeInfo[1], $routeInfo[2]),
                default => $this->createJson(500, ['error' => 'Internal Server Error']),
            };
        } catch (\Throwable $e) {
            ob_clean();
            // Log the throw — only message and class, not full trace (unless debug)
            try {
                $logger = $this->container->get(\Psr\Log\LoggerInterface::class);
                $logger->error('Unhandled exception', ['class' => get_class($e), 'message' => $e->getMessage()]);
            } catch (\Throwable) {
            }

            $response = $this->createJson(500, [
                'error' => 'Unhandled Exception',
                'message' => $this->config->get('app.debug', true) ? $e->getMessage() : 'Server Error',
                'trace' => $this->config->get('app.debug', false)
                    ? array_slice(explode("\n", $e->getTraceAsString()), 0, 10)
                    : null,
            ]);
        }

        $this->emitResponse($response);
        ob_end_flush();
    }


    // -------------------------------------------------------------------------
    // Route handler dispatcher
    // -------------------------------------------------------------------------

    private function handleFoundRoute(
        ServerRequestInterface $request,
        mixed $handler,
        array $vars
    ): ResponseInterface {
        if (is_array($handler) && isset($handler[0]) && class_exists($handler[0])) {
            $controller = $this->container->get($handler[0]);
            $handler = [$controller, $handler[1]];
        } elseif (is_string($handler) && str_contains($handler, '@')) {
            [$class, $method] = explode('@', $handler, 2);
            $handler = [$this->container->get($class), $method];
        }

        // Build the full security + bandwidth middleware pipeline from DI
        $middlewareStack = MiddlewareStack::fromContainer($this->container);

        $finalHandler = function (ServerRequestInterface $req) use ($handler, $vars): ResponseInterface {
            return call_user_func($handler, $req, $vars);
        };

        return $middlewareStack->handle($request, $finalHandler);
    }


    // -------------------------------------------------------------------------
    // Response helpers
    // -------------------------------------------------------------------------

    public function createJson(int $status, mixed $data): ResponseInterface
    {
        $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
        $body = $factory->createStream(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withBody($body);
    }

    private function emitResponse(ResponseInterface $response): void
    {
        if (headers_sent()) {
            return;
        }

        http_response_code($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $values) {
            $first = true;
            foreach ($values as $value) {
                header("$name: $value", $first);
                $first = false;
            }
        }

        $elapsed = round((hrtime(true) / 1e6) - self::$bootTime, 3);
        header("X-Response-Time: {$elapsed}ms", true);

        echo $response->getBody();
    }
}

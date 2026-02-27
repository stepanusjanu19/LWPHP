<?php

namespace Kei\Lwphp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareStack
{
    /** @var MiddlewareInterface[] */
    private array $middlewares = [];

    /**
     * Push a PSR-15 middleware onto the stack.
     */
    public function add(MiddlewareInterface $middleware): void
    {
        $this->middlewares[] = $middleware;
    }

    /**
     * Run the request through the middleware stack and return a response.
     *
     * Middlewares are executed in FIFO order (first added = outermost wrap).
     *
     * @param ServerRequestInterface $request
     * @param callable(ServerRequestInterface): ResponseInterface $handler Final handler
     */
    public function handle(ServerRequestInterface $request, callable $handler): ResponseInterface
    {
        // Wrap the callable final handler into a PSR-15 RequestHandlerInterface
        $next = new class ($handler) implements RequestHandlerInterface {
            public function __construct(private readonly \Closure $handler)
            {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->handler)($request);
            }
        };

        // Build the middleware chain in reverse so first-added runs first
        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = new class ($middleware, $next) implements RequestHandlerInterface {
                public function __construct(
                private readonly MiddlewareInterface $middleware,
                private readonly RequestHandlerInterface $next,
                ) {}

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $next->handle($request);
    }

    /**
     * Build the standard LWPHP middleware stack from the DI container.
     */
    public static function fromContainer(\DI\Container $c): self
    {
        $stack = new self();
        // Outermost first: bandwidth wraps everything (measures full latency + compresses final response)
        $stack->add($c->get(BandwidthMiddleware::class));
        // Security runs inside bandwidth so gzip is applied AFTER security checks
        $stack->add($c->get(SecurityMiddleware::class));
        // Auth covers protected dashboard/cms routes
        $stack->add($c->get(AuthMiddleware::class));
        return $stack;
    }
}
<?php

use Kei\Lwphp\Middleware\TrafficShaperMiddleware;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

test('TrafficShaperMiddleware allows requests under the limit', function () {
    $cache = new ArrayAdapter();
    // High limit, low threshold
    $middleware = new TrafficShaperMiddleware($cache, 10, 5, 0);

    $factory = new Psr17Factory();
    $request = $factory->createServerRequest('GET', '/api/test');

    $handler = new class ($factory) implements RequestHandlerInterface {
        public function __construct(private $factory)
        {}
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return $this->factory->createResponse(200);
        }
    };

    $response = $middleware->process($request, $handler);
    expect($response->getStatusCode())->toBe(200);
    expect($response->getHeaderLine('X-RateLimit-Limit'))->toBe('10');
    expect($response->getHeaderLine('X-RateLimit-Remaining'))->toBe('9');
});

test('TrafficShaperMiddleware blocks requests over the limit with 429', function () {
    $cache = new ArrayAdapter();
    // Very low limit to trigger 429
    $middleware = new TrafficShaperMiddleware($cache, 2, 5, 0);

    $factory = new Psr17Factory();
    $request = $factory->createServerRequest('GET', '/api/test');

    $handler = new class ($factory) implements RequestHandlerInterface {
        public function __construct(private $factory)
        {}
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return $this->factory->createResponse(200);
        }
    };

    // 1st request
    $middleware->process($request, $handler);
    // 2nd request
    $middleware->process($request, $handler);

    // 3rd request should be blocked
    $response = $middleware->process($request, $handler);

    expect($response->getStatusCode())->toBe(429);
    expect($response->getHeaderLine('X-RateLimit-Remaining'))->toBe('0');
});

test('TrafficShaperMiddleware applies throttle delay when threshold is exceeded', function () {
    $cache = new ArrayAdapter();
    // Delay of 200ms
    $middleware = new TrafficShaperMiddleware($cache, 10, 1, 200);

    $factory = new Psr17Factory();
    $request = $factory->createServerRequest('GET', '/api/test');

    $handler = new class ($factory) implements RequestHandlerInterface {
        public function __construct(private $factory)
        {}
        public function handle(\Psr\Http\Message\ServerRequestInterface $request): \Psr\Http\Message\ResponseInterface
        {
            return $this->factory->createResponse(200);
        }
    };

    // 1st request (no delay, hits=1)
    $middleware->process($request, $handler);

    // 2nd request should trigger a >= 200ms delay since threshold is 1
    $start = microtime(true);
    $response = $middleware->process($request, $handler);
    $duration = (microtime(true) - $start) * 1000;

    expect($response->getStatusCode())->toBe(200);
    expect($duration)->toBeGreaterThanOrEqual(190); // 190ms to allow for slight timer variances
});

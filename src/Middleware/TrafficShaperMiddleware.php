<?php

namespace Kei\Lwphp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Traffic Shaper Middleware
 * Manages traffic, latency, stress, and bandwidth by rate limiting
 * and applying backpressure (artificial latency) during traffic spikes.
 */
class TrafficShaperMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly int $maxRequestsPerMinute = 60,
        private readonly int $throttleThreshold = 40,
        private readonly int $throttleDelayMs = 500
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown_ip';
        $minute = (int) (time() / 60);
        $cacheKey = "ts_rate_limit_{$ip}_{$minute}";

        // Note: Using standard PSR-6/16 get/delete to simulate an incrementable counter.
        // For production TokenBucket, use Redis adapter's native increment.
        $hits = $this->cache->get($cacheKey, function (ItemInterface $item) {
            $item->expiresAfter(120);
            return 0;
        });

        $hits++;

        $this->cache->delete($cacheKey);
        $this->cache->get($cacheKey, function (ItemInterface $item) use ($hits) {
            $item->expiresAfter(120);
            return $hits;
        });

        if ($hits > $this->maxRequestsPerMinute) {
            $factory = new Psr17Factory();
            $body = $factory->createStream(json_encode([
                'error' => 'Too Many Requests',
                'message' => 'Traffic shaper dropped request to protect server bandwidth.'
            ]));
            return $factory->createResponse(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string) (60 - (time() % 60)))
                ->withHeader('X-RateLimit-Limit', (string) $this->maxRequestsPerMinute)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withBody($body);
        }

        // Apply backpressure (throttle latency) if traffic is spiking to save bandwidth/stress
        if ($hits > $this->throttleThreshold && $this->throttleDelayMs > 0) {
            usleep($this->throttleDelayMs * 1000); // Delay in microseconds
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string) $this->maxRequestsPerMinute)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $this->maxRequestsPerMinute - $hits));
    }
}

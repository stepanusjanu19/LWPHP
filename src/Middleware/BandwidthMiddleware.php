<?php

namespace Kei\Lwphp\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * BandwidthMiddleware — response compression + latency monitoring.
 *
 * Features:
 *  - Gzip compression when client sends `Accept-Encoding: gzip`
 *  - Adds `Content-Length` header (before optional compression)
 *  - Adds `X-Response-Time` (total time including security middleware)
 *  - Logs a WARNING when response time exceeds the slow-request threshold
 *  - Sets `Cache-Control` headers for GET responses
 */
class BandwidthMiddleware implements MiddlewareInterface
{
    private const SLOW_THRESHOLD_MS = 3000; // warn if request takes > 3s

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $slowThresholdMs = self::SLOW_THRESHOLD_MS,
        private readonly bool $gzipEnabled = true,
        private readonly int $compressionLevel = 6,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $start = hrtime(true);
        $response = $handler->handle($request);
        $elapsed = round((hrtime(true) - $start) / 1e6, 3);

        // ── Slow request detection ─────────────────────────────────────────────
        if ($elapsed > $this->slowThresholdMs) {
            $this->logger->warning('Slow response detected', [
                'path' => $request->getUri()->getPath(),
                'method' => $request->getMethod(),
                'elapsed_ms' => $elapsed,
                'threshold' => $this->slowThresholdMs,
            ]);
        }

        $response = $response->withHeader('X-Response-Time', "{$elapsed}ms");

        // ── Gzip compression ───────────────────────────────────────────────────
        if ($this->gzipEnabled && $this->clientAcceptsGzip($request)) {
            $body = (string) $response->getBody();
            $ct = $response->getHeaderLine('Content-Type');
            $compress = str_contains($ct, 'json') || str_contains($ct, 'text') || str_contains($ct, 'xml');

            if ($compress && strlen($body) > 860) { // only compress if worth it (>860B)
                $compressed = gzencode($body, $this->compressionLevel);
                if ($compressed !== false) {
                    $factory = new \Nyholm\Psr7\Factory\Psr17Factory();
                    $response = $response
                        ->withBody($factory->createStream($compressed))
                        ->withHeader('Content-Encoding', 'gzip')
                        ->withHeader('Vary', 'Accept-Encoding')
                        ->withHeader('Content-Length', (string) strlen($compressed));
                }
            } else {
                $response = $response->withHeader('Content-Length', (string) strlen($body));
            }
        }

        // ── Cache-Control for GET ──────────────────────────────────────────────
        if (
            strtoupper($request->getMethod()) === 'GET'
            && !$response->hasHeader('Cache-Control')
        ) {
            $response = $response->withHeader('Cache-Control', 'no-cache, no-store, must-revalidate');
        }

        return $response;
    }

    private function clientAcceptsGzip(ServerRequestInterface $request): bool
    {
        return str_contains($request->getHeaderLine('Accept-Encoding'), 'gzip');
    }
}

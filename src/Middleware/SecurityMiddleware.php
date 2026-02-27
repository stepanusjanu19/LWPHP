<?php

namespace Kei\Lwphp\Middleware;

use Kei\Lwphp\Core\ConfigLoader;
use Kei\Lwphp\Security\AnomalyDetector;
use Kei\Lwphp\Security\IpFilter;
use Kei\Lwphp\Security\InputSanitizer;
use Kei\Lwphp\Security\RateLimiter;
use Kei\Lwphp\Security\SecurityException;
use Kei\Lwphp\Security\SsrfGuard;
use Kei\Lwphp\Security\ThreatLogger;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;

/**
 * SecurityMiddleware — Runs on every HTTP request.
 *
 * Pipeline:
 *  1. CORS preflight (OPTIONS → 204)
 *  2. Request size limit
 *  3. IP filter (blocklist / allowlist)
 *  4. Rate limiting (sliding window per IP)
 *  4.5 Anomaly auto-block check (behavioral: repeated 4xx, scanner pattern)
 *  5. Content-Type / XXE check
 *  6. Input sanitization (XSS strip, SQLi/path/null detect)
 *  7. SSRF guard on URL fields
 *  8. Record 4xx/5xx for anomaly tracking + add security headers to response
 */
class SecurityMiddleware implements MiddlewareInterface
{
    private readonly Psr17Factory $factory;

    public function __construct(
        private readonly ConfigLoader $config,
        private readonly RateLimiter $rateLimiter,
        private readonly IpFilter $ipFilter,
        private readonly InputSanitizer $sanitizer,
        private readonly SsrfGuard $ssrfGuard,
        private readonly AnomalyDetector $anomaly,
        private readonly ThreatLogger $threatLogger,
        private readonly LoggerInterface $logger,
    ) {
        $this->factory = new Psr17Factory();
    }


    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        // ── 1. CORS preflight ─────────────────────────────────────────────────
        if ($method === 'OPTIONS') {
            return $this->corsResponse();
        }

        // ── 2. Request size ───────────────────────────────────────────────────
        $maxBytes = ((int) $this->config->get('security.max_body_kb', 1024)) * 1024;
        $clStr = $request->getHeaderLine('Content-Length');
        if ($clStr !== '' && (int) $clStr > $maxBytes) {
            return $this->secError(413, 'Request body too large.');
        }

        // ── 3. IP filter ──────────────────────────────────────────────────────
        $ip = $this->clientIp($request);
        $method = strtoupper($request->getMethod());

        if ($this->ipFilter->isDenied($ip)) {
            $this->threatLogger->log('ip_blocked', $ip, $path, $method);
            return $this->secError(403, 'Access denied — your IP is not permitted.');
        }

        // ── 4. Rate limit ────────────────────────────────────────────────────
        $exemptPaths = (array) $this->config->get('security.ratelimit_exempt_paths', []);
        if (!in_array($path, $exemptPaths, true) && !$this->rateLimiter->isAllowed($ip)) {
            $retry = $this->rateLimiter->retryAfterSeconds();
            $this->threatLogger->log('rate_limit', $ip, $path, $method, ['retry_after' => $retry]);
            return $this->secError(429, 'Too many requests. Slow down.')
                ->withHeader('Retry-After', (string) $retry)
                ->withHeader('X-RateLimit-Limit', (string) $this->config->get('security.rate_limit.max_requests', 180))
                ->withHeader('X-RateLimit-Window', (string) $this->config->get('security.rate_limit.window_seconds', 60));
        }

        // ── 4.5 Anomaly auto-block ─────────────────────────────────────────
        $this->anomaly->clearExpiredBlocks();
        if ($this->anomaly->isAutoBlocked($ip)) {
            $this->threatLogger->log('anomaly', $ip, $path, $method, ['reason' => 'auto_blocked']);
            return $this->secError(403, 'Access temporarily blocked due to suspicious activity.');
        }

        // ── 4.6 System overload guard ─────────────────────────────────────
        if ($this->anomaly->isSystemOverloaded() || $this->anomaly->isMemoryCritical()) {
            $this->threatLogger->log('overload', $ip, $path, $method, $this->anomaly->systemHealth());
            return $this->secError(503, 'Server is temporarily overloaded. Please retry shortly.')
                ->withHeader('Retry-After', '10');
        }

        // ── 5. Content-Type check (POST / PUT / PATCH only) ───────────────────
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $ct = strtolower($request->getHeaderLine('Content-Type'));
            if (
                $ct !== '' && !str_contains($ct, 'application/json')
                && !str_contains($ct, 'application/x-www-form-urlencoded')
                && !str_contains($ct, 'multipart/form-data')
            ) {
                // XXE: reject XML entirely
                if (str_contains($ct, 'xml')) {
                    $raw = (string) $request->getBody();
                    try {
                        $this->sanitizer->checkXxe($raw);
                    } catch (SecurityException $e) {
                        $this->logger->error('XXE attempt', ['ip' => $ip, 'path' => $path]);
                        return $this->secError($e->getHttpStatus(), $e->getMessage());
                    }
                }
            }
        }

        // ── 6. Input sanitization ─────────────────────────────────────────────
        $body = $this->parseBody($request);
        if (!empty($body)) {
            try {
                $sanitized = $this->sanitizer->sanitize($body);
                // Attach sanitized body to request attributes for controllers
                $request = $request->withAttribute('_sanitized_body', $sanitized);
            } catch (SecurityException $e) {
                // Detect which type of attack
                $type = match (true) {
                    str_contains($e->getMessage(), 'SQL injection') => 'sqli',
                    str_contains($e->getMessage(), 'XSS') => 'xss',
                    str_contains($e->getMessage(), 'XXE') => 'xxe',
                    str_contains($e->getMessage(), 'Path trav') => 'path_trav',
                    str_contains($e->getMessage(), 'Null byte') => 'null_byte',
                    str_contains($e->getMessage(), 'serialized') => 'php_inject',
                    default => 'input_attack',
                };
                $this->threatLogger->log($type, $ip, $path, $method, ['msg' => $e->getMessage()]);
                $this->anomaly->recordError($ip, $e->getHttpStatus(), $path, $method);
                return $this->secError($e->getHttpStatus(), $e->getMessage());
            }
        }

        // ── 7. SSRF guard ────────────────────────────────────────────────────
        if (!empty($body)) {
            try {
                $this->ssrfGuard->scanBody($body);
            } catch (SecurityException $e) {
                $this->threatLogger->log('ssrf', $ip, $path, $method, ['msg' => $e->getMessage()]);
                $this->anomaly->recordError($ip, 400, $path, $method);
                return $this->secError(400, $e->getMessage());
            }
        }

        // ── 8. Forward + record anomaly on 4xx/5xx + add security headers ───
        $response = $handler->handle($request);
        $status = $response->getStatusCode();
        if ($status >= 400) {
            $this->anomaly->recordError($ip, $status, $path, $method);
        }
        return $this->addSecurityHeaders($response);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function clientIp(ServerRequestInterface $request): string
    {
        // Respect trusted reverse-proxy headers in order of specificity
        foreach (['X-Real-IP', 'X-Forwarded-For', 'CF-Connecting-IP'] as $header) {
            $val = $request->getHeaderLine($header);
            if ($val !== '') {
                return trim(explode(',', $val)[0]);
            }
        }
        $params = $request->getServerParams();
        return (string) ($params['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    private function parseBody(ServerRequestInterface $request): array
    {
        $parsed = $request->getParsedBody();
        if (is_array($parsed) && !empty($parsed)) {
            return $parsed;
        }
        if (str_contains(strtolower($request->getHeaderLine('Content-Type')), 'application/json')) {
            $data = json_decode((string) $request->getBody(), true);
            return is_array($data) ? $data : [];
        }
        return [];
    }

    private function addSecurityHeaders(ResponseInterface $response): ResponseInterface
    {
        $origins = $this->config->get('security.cors_origins', ['*']);
        $origin = is_array($origins) ? implode(', ', $origins) : (string) $origins;

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('Permissions-Policy', 'geolocation=(), camera=(), microphone=()')
            ->withHeader(
                'Content-Security-Policy',
                "default-src 'self'; "
                . "script-src 'self' 'unsafe-inline' https://unpkg.com https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://fonts.googleapis.com; "
                . "style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com https://fonts.gstatic.com; "
                . "font-src 'self' https://fonts.gstatic.com; "
                . "img-src 'self' data:; connect-src 'self' https://unpkg.com;"
            );
    }

    private function corsResponse(): ResponseInterface
    {
        return $this->factory->createResponse(204)
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With')
            ->withHeader('Access-Control-Max-Age', '86400')
            ->withHeader('Content-Length', '0');
    }

    private function secError(int $status, string $msg): ResponseInterface
    {
        $body = $this->factory->createStream(json_encode([
            'error' => $msg,
            'status' => $status,
        ]));
        return $this->factory->createResponse($status)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withBody($body);
    }
}

<?php

namespace Kei\Lwphp\Security;

/**
 * ThreatLogger — Dedicated security event logging.
 *
 * Writes JSON-lines to storage/logs/threats.log (separate from app log).
 * Format: one JSON object per line, easy to parse with `jq`.
 *
 * Event types:
 *   rate_limit  — IP exceeded request quota
 *   ip_blocked  — IP matched blocklist / allowlist restriction
 *   sqli        — SQL injection pattern detected
 *   xss         — XSS pattern stripped
 *   xxe         — XXE DOCTYPE/ENTITY detected
 *   ssrf        — SSRF URL attempt blocked
 *   path_trav   — Path traversal attempt
 *   null_byte   — Null byte injection
 *   php_inject  — PHP serialized data detected
 *   anomaly     — IpBehavior anomaly (repeated 4xx, scanner pattern, etc.)
 *   overload    — System overload guard triggered
 */
class ThreatLogger
{
    public function __construct(
        private readonly string $logPath,
    ) {
        $dir = dirname($logPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    /**
     * Log a security event.
     *
     * @param string $type    One of the event types listed above
     * @param string $ip      Client IP address
     * @param string $path    Request path
     * @param string $method  HTTP method
     * @param array  $details Additional context (no PII, no request bodies)
     */
    public function log(
        string $type,
        string $ip,
        string $path,
        string $method = 'GET',
        array $details = []
    ): void {
        $entry = json_encode([
            'ts' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'type' => $type,
            'ip' => $ip,
            'method' => $method,
            'path' => $path,
            'details' => $details,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        @file_put_contents($this->logPath, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    /**
     * Return the last N threat events (most recent first).
     *
     * @return array<int, array<string, mixed>>
     */
    public function tail(int $lines = 50): array
    {
        if (!file_exists($this->logPath)) {
            return [];
        }

        $all = file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $recent = array_slice(array_reverse($all), 0, $lines);

        $events = [];
        foreach ($recent as $line) {
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                $events[] = $decoded;
            }
        }
        return $events;
    }

    /**
     * Count events by type within the last N minutes.
     *
     * @return array<string, int>
     */
    public function countByType(int $withinMinutes = 60): array
    {
        $since = time() - ($withinMinutes * 60);
        $counts = [];

        if (!file_exists($this->logPath)) {
            return $counts;
        }

        $lines = file($this->logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $event = json_decode($line, true);
            if (!is_array($event)) {
                continue;
            }
            $ts = strtotime($event['ts'] ?? '') ?: 0;
            if ($ts >= $since) {
                $type = $event['type'] ?? 'unknown';
                $counts[$type] = ($counts[$type] ?? 0) + 1;
            }
        }
        return $counts;
    }
}

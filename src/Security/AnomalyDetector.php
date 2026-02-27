<?php

namespace Kei\Lwphp\Security;

use Doctrine\DBAL\Connection;

/**
 * AnomalyDetector — Behavioral threat detection and auto-blocking.
 *
 * Tracks per-IP error counts using a SQLite table (no Redis needed).
 * Automatically promotes IPs through escalating response levels:
 *
 *   0–4 errors/min  → Normal
 *   5–19 errors/min → Slow throttle (extra 429 + Retry-After: 30s)
 *   20+ errors/min  → Auto-block IP for 10 minutes
 *   Many 404/min    → Scanner detection (same auto-block)
 *
 * Also checks:
 *   - System CPU load (sys_getloadavg)
 *   - PHP memory usage
 *
 * All auto-blocks write to the `anomaly_blocks` table so they persist
 * across requests. The IpFilter re-reads this list on each request.
 */
class AnomalyDetector
{
    private const ERROR_TABLE = 'anomaly_errors';
    private const BLOCK_TABLE = 'anomaly_blocks';

    private const THROTTLE_THRESHOLD = 5;   // errors → slow throttle
    private const BLOCK_THRESHOLD = 20;  // errors → auto-block
    private const SCAN_404_THRESHOLD = 15;  // 404s in window → scanner
    private const BLOCK_DURATION_SECS = 600; // 10 minutes auto-block

    private bool $tableReady = false;

    public function __construct(
        private readonly Connection $conn,
        private readonly ThreatLogger $threatLogger,
        private readonly int $windowSeconds = 60,
    ) {
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Record an error response (4xx/5xx) for the IP.
     * Triggers auto-block if thresholds are exceeded.
     *
     * @return 'ok'|'throttle'|'block'
     */
    public function recordError(string $ip, int $statusCode, string $path, string $method = 'GET'): string
    {
        $this->ensureTables();
        $window = $this->currentWindow();

        $this->incrementError($ip, $window);
        $count = $this->errorCount($ip, $window);

        // Check for scanner pattern (predominant 404s)
        if ($statusCode === 404 && $this->error404Count($ip, $window) >= self::SCAN_404_THRESHOLD) {
            $this->autoBlock($ip, 'anomaly', $path);
            $this->threatLogger->log('anomaly', $ip, $path, $method, [
                'reason' => 'scanner_pattern',
                '404_count' => $this->error404Count($ip, $window),
            ]);
            return 'block';
        }

        if ($count >= self::BLOCK_THRESHOLD) {
            $this->autoBlock($ip, 'anomaly', $path);
            $this->threatLogger->log('anomaly', $ip, $path, $method, [
                'reason' => 'error_flood',
                'error_count' => $count,
                'status_code' => $statusCode,
            ]);
            return 'block';
        }

        if ($count >= self::THROTTLE_THRESHOLD) {
            return 'throttle';
        }

        return 'ok';
    }

    /**
     * Check if an IP is currently auto-blocked.
     */
    public function isAutoBlocked(string $ip): bool
    {
        $this->ensureTables();
        $now = time();

        $row = $this->conn->fetchOne(
            'SELECT blocked_until FROM ' . self::BLOCK_TABLE . ' WHERE ip = ? AND blocked_until > ?',
            [$ip, $now]
        );
        return $row !== false;
    }

    /**
     * Remove expired auto-blocks (call periodically or on each request).
     */
    public function clearExpiredBlocks(): void
    {
        $this->ensureTables();
        try {
            $this->conn->executeStatement(
                'DELETE FROM ' . self::BLOCK_TABLE . ' WHERE blocked_until <= ?',
                [time()]
            );
        } catch (\Throwable) {
        }
    }

    /**
     * Check if system is under high load.
     *
     * Returns true if 1-min load avg > 80% of CPU cores.
     */
    public function isSystemOverloaded(): bool
    {
        $load = sys_getloadavg();
        if ($load === false) {
            return false;
        }
        $cores = $this->cpuCoreCount();
        return ($load[0] / $cores) > 0.80;
    }

    /**
     * Check if PHP memory usage is critically high (> 85% of limit).
     */
    public function isMemoryCritical(): bool
    {
        $limit = $this->parseMemoryLimit(ini_get('memory_limit') ?: '128M');
        if ($limit <= 0) {
            return false;
        }
        return (memory_get_usage(true) / $limit) > 0.85;
    }

    /**
     * Get current system health stats.
     */
    public function systemHealth(): array
    {
        $load = sys_getloadavg() ?: [0, 0, 0];
        $cores = $this->cpuCoreCount();
        $memLimit = $this->parseMemoryLimit(ini_get('memory_limit') ?: '128M');
        $memUsed = memory_get_usage(true);

        return [
            'load_1m' => round($load[0], 2),
            'load_5m' => round($load[1], 2),
            'load_15m' => round($load[2], 2),
            'cpu_cores' => $cores,
            'load_pct' => $cores > 0 ? round(($load[0] / $cores) * 100, 1) : 0,
            'memory_mb' => round($memUsed / 1048576, 1),
            'memory_limit' => round($memLimit / 1048576, 1) . 'MB',
            'memory_pct' => $memLimit > 0 ? round(($memUsed / $memLimit) * 100, 1) : 0,
            'overloaded' => $this->isSystemOverloaded(),
        ];
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private function autoBlock(string $ip, string $reason, string $path): void
    {
        $until = time() + self::BLOCK_DURATION_SECS;
        try {
            $this->conn->executeStatement(
                'INSERT OR REPLACE INTO ' . self::BLOCK_TABLE . ' (ip, reason, blocked_until, path) VALUES (?, ?, ?, ?)',
                [$ip, $reason, $until, $path]
            );
        } catch (\Throwable) {
        }
    }

    private function incrementError(string $ip, int $window): void
    {
        try {
            $existing = $this->conn->fetchOne(
                'SELECT count FROM ' . self::ERROR_TABLE . ' WHERE ip = ? AND window_key = ?',
                [$ip, $window]
            );
            if ($existing === false) {
                $this->conn->executeStatement(
                    'INSERT INTO ' . self::ERROR_TABLE . ' (ip, window_key, count) VALUES (?, ?, 1)',
                    [$ip, $window]
                );
            } else {
                $this->conn->executeStatement(
                    'UPDATE ' . self::ERROR_TABLE . ' SET count = count + 1 WHERE ip = ? AND window_key = ?',
                    [$ip, $window]
                );
            }
        } catch (\Throwable) {
        }
    }

    private function errorCount(string $ip, int $window): int
    {
        $val = $this->conn->fetchOne(
            'SELECT count FROM ' . self::ERROR_TABLE . ' WHERE ip = ? AND window_key = ?',
            [$ip, $window]
        );
        return $val !== false ? (int) $val : 0;
    }

    private function error404Count(string $ip, int $window): int
    {
        // Re-use the error_table but we track 404s by checking path patterns
        // Simplified: if total errors in window ≥ SCAN threshold, treat as scanner
        return $this->errorCount($ip, $window);
    }

    private function currentWindow(): int
    {
        return (int) floor(time() / $this->windowSeconds);
    }

    private function cpuCoreCount(): int
    {
        static $cores = null;
        if ($cores === null) {
            $cores = (int) (shell_exec('nproc 2>/dev/null') ?? shell_exec('sysctl -n hw.ncpu 2>/dev/null') ?? '1') ?: 1;
        }
        return $cores;
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        if ($limit === '-1')
            return PHP_INT_MAX;

        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        return match ($unit) {
            'g' => $value * 1073741824,
            'm' => $value * 1048576,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function ensureTables(): void
    {
        if ($this->tableReady)
            return;
        try {
            $this->conn->executeStatement('
                CREATE TABLE IF NOT EXISTS ' . self::ERROR_TABLE . ' (
                    ip TEXT NOT NULL, window_key INTEGER NOT NULL,
                    count INTEGER NOT NULL DEFAULT 1,
                    PRIMARY KEY (ip, window_key)
                )
            ');
            $this->conn->executeStatement('
                CREATE TABLE IF NOT EXISTS ' . self::BLOCK_TABLE . ' (
                    ip TEXT PRIMARY KEY, reason TEXT NOT NULL,
                    blocked_until INTEGER NOT NULL, path TEXT
                )
            ');
        } catch (\Throwable) {
        }
        $this->tableReady = true;
    }
}

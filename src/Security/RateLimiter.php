<?php

namespace Kei\Lwphp\Security;

use Doctrine\DBAL\Connection;

/**
 * RateLimiter — SQLite-backed sliding-window rate limiter.
 *
 * Uses the DBAL connection directly (no Doctrine ORM overhead).
 * Creates the `rate_limits` table on first use.
 *
 * Algorithm: fixed 1-minute windows.
 *   - Increment counter for (ip, window_key)
 *   - Reject if counter > max_requests + burst_bonus
 *   - Purge stale windows on every N-th call
 */
class RateLimiter
{
    private const TABLE = 'rate_limits';

    private bool $tableReady = false;
    private int $callCount = 0;

    public function __construct(
        private readonly Connection $conn,
        private readonly int $windowSeconds = 60,
        private readonly int $maxRequests = 180,
        private readonly int $burstBonus = 20,
    ) {
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    /**
     * Check whether the given IP is within the rate limit.
     * Returns true if allowed, false if throttled.
     */
    public function isAllowed(string $ip): bool
    {
        $this->ensureTable();
        $this->maybePurge();

        $window = $this->currentWindow();
        $limit = $this->maxRequests + $this->burstBonus;

        // Upsert — increment or insert
        try {
            $existing = $this->conn->fetchOne(
                'SELECT request_count FROM ' . self::TABLE . ' WHERE ip = ? AND window_key = ?',
                [$ip, $window]
            );

            if ($existing === false) {
                $this->conn->executeStatement(
                    'INSERT INTO ' . self::TABLE . ' (ip, window_key, request_count) VALUES (?, ?, 1)',
                    [$ip, $window]
                );
                return true;
            }

            $newCount = (int) $existing + 1;
            $this->conn->executeStatement(
                'UPDATE ' . self::TABLE . ' SET request_count = ? WHERE ip = ? AND window_key = ?',
                [$newCount, $ip, $window]
            );

            return $newCount <= $limit;

        } catch (\Throwable) {
            return true; // fail open — never block on DB error
        }
    }

    /**
     * Seconds until the current window expires (for Retry-After header).
     */
    public function retryAfterSeconds(): int
    {
        return $this->windowSeconds - (time() % $this->windowSeconds);
    }

    /**
     * Current request count for this IP in the current window.
     */
    public function currentCount(string $ip): int
    {
        $this->ensureTable();
        $val = $this->conn->fetchOne(
            'SELECT request_count FROM ' . self::TABLE . ' WHERE ip = ? AND window_key = ?',
            [$ip, $this->currentWindow()]
        );
        return $val !== false ? (int) $val : 0;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    private function currentWindow(): int
    {
        return (int) floor(time() / $this->windowSeconds);
    }

    private function ensureTable(): void
    {
        if ($this->tableReady) {
            return;
        }
        try {
            $this->conn->executeStatement('
                CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                    ip            TEXT    NOT NULL,
                    window_key    INTEGER NOT NULL,
                    request_count INTEGER NOT NULL DEFAULT 1,
                    PRIMARY KEY (ip, window_key)
                )
            ');
        } catch (\Throwable) {
        }
        $this->tableReady = true;
    }

    /** Purge rows older than 2 windows, every 100 calls to avoid hot-path overhead */
    private function maybePurge(): void
    {
        $this->callCount++;
        if ($this->callCount % 100 !== 0) {
            return;
        }
        try {
            $staleWindow = $this->currentWindow() - 2;
            $this->conn->executeStatement(
                'DELETE FROM ' . self::TABLE . ' WHERE window_key < ?',
                [$staleWindow]
            );
        } catch (\Throwable) {
        }
    }
}

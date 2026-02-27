<?php

namespace Kei\Lwphp\Async;

use Kei\Lwphp\Core\ConfigLoader;

/**
 * JobDispatcher
 *
 * Single entry point for dispatching work synchronously or asynchronously.
 *
 * Modes:
 *   sync     → runs the callable immediately in the current process
 *   async    → runs jobs cooperatively via FiberPool (single-thread concurrency)
 *   parallel → runs jobs in forked child processes via pcntl (true parallelism)
 */
class JobDispatcher
{
    public function __construct(private readonly ConfigLoader $config)
    {
    }

    // -------------------------------------------------------------------------
    // Sync execution
    // -------------------------------------------------------------------------

    /**
     * Run a single callable synchronously and return timing + result.
     *
     * @return array{result: mixed, elapsed_ms: float}
     */
    public function runSync(callable $task, mixed ...$args): array
    {
        $start = hrtime(true);
        $result = $task(...$args);
        $ms = (hrtime(true) - $start) / 1e6;

        return ['result' => $result, 'elapsed_ms' => round($ms, 3)];
    }

    /**
     * Run multiple callables synchronously (sequential, no concurrency).
     *
     * @param  array<string, \Closure> $jobs  name → callable
     * @return array{name: string, result: mixed, elapsed_ms: float}[]
     */
    public function runSyncBatch(array $jobs): array
    {
        $results = [];
        foreach ($jobs as $name => $task) {
            $start = hrtime(true);
            $result = $task();
            $elapsed = (hrtime(true) - $start) / 1e6;
            $results[] = [
                'name' => $name,
                'result' => $result,
                'elapsed_ms' => round($elapsed, 3),
            ];
        }
        return $results;
    }

    // -------------------------------------------------------------------------
    // Async (Fiber cooperative)
    // -------------------------------------------------------------------------

    /**
     * Run multiple callables cooperatively via PHP 8.2 Fibers.
     *
     * @param  array<string, \Closure> $jobs
     * @param  int $maxConcurrent  max Fibers active at once
     */
    public function runAsync(array $jobs, int $maxConcurrent = 10): array
    {
        $pool = new FiberPool($maxConcurrent);
        foreach ($jobs as $name => $task) {
            $pool->add($name, $task);
        }
        return $pool->run();
    }

    // -------------------------------------------------------------------------
    // Parallel (pcntl fork)
    // -------------------------------------------------------------------------

    /**
     * Run multiple callables in separate forked processes.
     * Falls back to async Fiber mode if pcntl is unavailable.
     *
     * @param array<string, \Closure> $jobs
     */
    public function runParallel(array $jobs): array
    {
        $pool = new FiberPool();
        foreach ($jobs as $name => $task) {
            $pool->add($name, $task);
        }
        return $pool->runParallel();
    }

    // -------------------------------------------------------------------------
    // Summary helpers
    // -------------------------------------------------------------------------

    /**
     * Calculate wall-clock total of a result set (max of parallel, sum of serial).
     */
    public static function totalMs(array $results): float
    {
        return round(array_sum(array_column($results, 'elapsed_ms')), 3);
    }

    public static function maxMs(array $results): float
    {
        return round(max(array_column($results, 'elapsed_ms')), 3);
    }
}

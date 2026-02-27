<?php

namespace Kei\Lwphp\Base;

use Psr\Log\LoggerInterface;

/**
 * Abstract Worker — SOLID base for background job processing loops.
 *
 * Provides:
 *   - Continuous run() loop with configurable sleep and max-jobs limit
 *   - SIGTERM / SIGINT graceful shutdown (pcntl if available)
 *   - Per-iteration statistics (processed, failed, elapsed)
 *   - onShutdown() hook for cleanup
 *
 * Concrete workers extend this and implement processOne() to claim
 * and execute the next job from their specific queue.
 *
 * Usage:
 *   class QueueWorker extends Worker {
 *       public function __construct(
 *           private readonly JobQueue $queue,
 *           LoggerInterface $logger
 *       ) {
 *           parent::__construct($logger);
 *       }
 *       protected function processOne(): mixed {
 *           return $this->queue->processNext();
 *       }
 *   }
 *
 *   (new QueueWorker($queue, $logger))->run();
 */
abstract class Worker
{
    protected bool $running = true;
    protected int $processed = 0;
    protected int $failed = 0;

    private float $startedAt = 0.0;

    public function __construct(
        protected readonly LoggerInterface $logger,
    ) {
    }

    // ── Must override ─────────────────────────────────────────────────────────

    /**
     * Claim and process the next available job.
     *
     * Return the processed item (any non-null) to signal work was done.
     * Return null when the queue is empty (worker will sleep before retry).
     */
    abstract protected function processOne(): mixed;

    // ── Optional hooks ────────────────────────────────────────────────────────

    /** Called after run() exits (graceful shutdown). Override for cleanup. */
    protected function onShutdown(): void
    {
    }

    /** Called after each successful processOne(). Override for monitoring. */
    protected function onSuccess(mixed $result): void
    {
    }

    /** Called after a failed processOne(). Override for alerting. */
    protected function onFailure(\Throwable $e): void
    {
    }

    // ── Main loop ─────────────────────────────────────────────────────────────

    /**
     * Start the worker loop.
     *
     * @param int $maxJobs  0 = run forever; N = stop after N jobs
     * @param int $sleepMs  Milliseconds to sleep when queue is empty
     */
    public function run(int $maxJobs = 0, int $sleepMs = 500): void
    {
        $this->startedAt = hrtime(true) / 1e9;
        $this->setupSignalHandlers();

        $this->logger->info('Worker started', [
            'max_jobs' => $maxJobs ?: 'unlimited',
            'sleep_ms' => $sleepMs,
        ]);

        while ($this->running) {
            if (function_exists('pcntl_signal_dispatch')) {
                pcntl_signal_dispatch();
            }

            try {
                $result = $this->processOne();

                if ($result !== null) {
                    $this->processed++;
                    $this->onSuccess($result);

                    if ($maxJobs > 0 && $this->processed >= $maxJobs) {
                        $this->logger->info('Worker reached max-jobs limit', [
                            'processed' => $this->processed,
                            'max_jobs' => $maxJobs,
                        ]);
                        break;
                    }
                } else {
                    // Queue empty — sleep before next poll
                    usleep($sleepMs * 1000);
                }
            } catch (\Throwable $e) {
                $this->failed++;
                $this->logger->error('Worker loop error', [
                    'error' => $e->getMessage(),
                    'class' => get_class($e),
                ]);
                $this->onFailure($e);
                usleep($sleepMs * 1000); // back off on error
            }
        }

        $elapsed = round((hrtime(true) / 1e9) - $this->startedAt, 2);
        $this->logger->info('Worker stopped', [
            'processed' => $this->processed,
            'failed' => $this->failed,
            'elapsed_s' => $elapsed,
        ]);

        $this->onShutdown();
    }

    /** Stop the worker gracefully after the current job completes. */
    public function stop(): void
    {
        $this->running = false;
    }

    public function getProcessed(): int
    {
        return $this->processed;
    }
    public function getFailed(): int
    {
        return $this->failed;
    }

    public function getUptime(): float
    {
        return $this->startedAt > 0
            ? round((hrtime(true) / 1e9) - $this->startedAt, 2)
            : 0.0;
    }

    // ── Signal handling ───────────────────────────────────────────────────────

    private function setupSignalHandlers(): void
    {
        if (!function_exists('pcntl_signal')) {
            return;
        }
        $stop = function () {
            $this->logger->info('Worker received shutdown signal');
            $this->running = false;
        };
        pcntl_signal(SIGTERM, $stop);
        pcntl_signal(SIGINT, $stop);
    }
}

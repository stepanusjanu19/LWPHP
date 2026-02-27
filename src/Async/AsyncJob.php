<?php

namespace Kei\Lwphp\Async;

/**
 * AsyncJob
 *
 * Wraps a callable inside a PHP 8.2 Fiber so it can be run
 * cooperatively alongside other jobs without blocking the event loop.
 */
class AsyncJob
{
    private \Fiber $fiber;
    private mixed $result = null;
    private ?\Throwable $error = null;
    private float $startedAt = 0.0;
    private float $finishedAt = 0.0;

    public function __construct(
        private readonly string $name,
        private readonly \Closure $task,
    ) {
        $this->fiber = new \Fiber($this->task);
    }

    /**
     * Start the Fiber (non-blocking until it yields or returns).
     */
    public function start(mixed ...$args): void
    {
        $this->startedAt = hrtime(true);
        if ($this->fiber->isStarted()) {
            return;
        }
        try {
            $this->result = $this->fiber->start(...$args);
        } catch (\Throwable $e) {
            $this->error = $e;
            $this->finishedAt = hrtime(true);
        }
        if ($this->fiber->isTerminated()) {
            $this->result = $this->fiber->getReturn();
            $this->finishedAt = hrtime(true);
        }
    }

    /**
     * Resume a suspended Fiber (pass a value from the scheduler).
     */
    public function resume(mixed $value = null): void
    {
        if (!$this->fiber->isSuspended()) {
            return;
        }
        try {
            $this->fiber->resume($value);
        } catch (\Throwable $e) {
            $this->error = $e;
            $this->finishedAt = hrtime(true);
        }
        if ($this->fiber->isTerminated()) {
            $this->result = $this->fiber->getReturn();
            $this->finishedAt = hrtime(true);
        }
    }

    public function isTerminated(): bool
    {
        return $this->fiber->isTerminated();
    }
    public function isSuspended(): bool
    {
        return $this->fiber->isSuspended();
    }
    public function isRunning(): bool
    {
        return $this->fiber->isRunning();
    }
    public function getResult(): mixed
    {
        return $this->result;
    }
    public function getError(): ?\Throwable
    {
        return $this->error;
    }
    public function getName(): string
    {
        return $this->name;
    }

    /** Elapsed milliseconds (0 if not finished) */
    public function elapsedMs(): float
    {
        $end = $this->finishedAt ?: hrtime(true);
        return ($end - $this->startedAt) / 1e6;
    }
}

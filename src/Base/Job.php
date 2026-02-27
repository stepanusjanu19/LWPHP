<?php

namespace Kei\Lwphp\Base;

/**
 * Abstract Job — SOLID base for all background job types.
 *
 * Concrete jobs extend this class, implement handle(), and register
 * with the JobQueue via the job name returned by getName().
 *
 * Lifecycle:
 *   1. JobQueue::dispatch($name, $payload) creates/persists an Entity\Job
 *   2. Worker::processOne() claims the entity, instantiates the concrete Job,
 *      calls handle(), then onSuccess() or onFailure()
 *
 * Usage:
 *   class PrimesJob extends Job {
 *       public function handle(): mixed {
 *           return computePrimes($this->payload['limit'] ?? 10_000);
 *       }
 *   }
 */
abstract class Job
{
    public function __construct(
        protected readonly string $name,
        protected readonly array $payload = [],
    ) {
    }

    // ── Must override ─────────────────────────────────────────────────────────

    /**
     * Execute the job and return its result.
     * Result is serialized and stored in Entity\Job::$result.
     *
     * @throws \Throwable — caught by the worker and stored as error
     */
    abstract public function handle(): mixed;

    // ── Optional hooks ─────────────────────────────────────────────────────────

    /** Called after a successful handle(). Override for side-effects. */
    public function onSuccess(mixed $result): void
    {
    }

    /** Called when handle() throws. Override for cleanup / retry logic. */
    public function onFailure(\Throwable $e): void
    {
    }

    // ── Accessors ─────────────────────────────────────────────────────────────

    public function getName(): string
    {
        return $this->name;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    /** Short display name (last segment of class name). */
    public function getShortName(): string
    {
        return basename(str_replace('\\', '/', static::class));
    }

    /** Serialize to primitives for storage / logging. */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'payload' => $this->payload,
            'class' => static::class,
        ];
    }
}

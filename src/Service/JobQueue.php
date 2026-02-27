<?php

namespace Kei\Lwphp\Service;

use Kei\Lwphp\Base\Service as BaseService;
use Kei\Lwphp\Entity\Job;
use Kei\Lwphp\Repository\JobRepository;
use Psr\Log\LoggerInterface;

/**
 * JobQueue — Background job dispatch and execution service.
 *
 * Extends Base\Service for structured logging.
 * Delegates queue storage to JobRepository.
 * delegate job execution to HeavyJobService handler map.
 */
class JobQueue extends BaseService
{
    /** Registered job names → HeavyJobService methods */
    public const JOBS = ['primes', 'matrix', 'hash', 'fibonacci', 'sort'];

    public function __construct(
        private readonly JobRepository $repo,
        private readonly HeavyJobService $heavy,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    // ── Public API ────────────────────────────────────────────────────────────

    /**
     * Push a named job to the queue (returns immediately).
     *
     * @throws \InvalidArgumentException for unknown job names
     */
    public function dispatch(string $name, array $payload = []): Job
    {
        if (!in_array($name, self::JOBS, true)) {
            $this->logError('job.invalid', new \InvalidArgumentException("Unknown job '{$name}'"));
            throw new \InvalidArgumentException(
                "Unknown job '{$name}'. Available: " . implode(', ', self::JOBS)
            );
        }

        $job = $this->repo->push($name, $payload);
        $this->logSuccess('job.dispatched', ['id' => $job->getId(), 'name' => $name]);
        return $job;
    }

    /**
     * Claim and execute the next pending job (called by worker).
     *
     * Returns the processed Job, or null if the queue is empty.
     */
    public function processNext(): ?Job
    {
        $job = $this->repo->fetchNext();
        if ($job === null) {
            return null;
        }

        $start = hrtime(true);
        try {
            $handlers = $this->heavy->allJobs();
            if (!isset($handlers[$job->getName()])) {
                throw new \RuntimeException("No handler for job '{$job->getName()}'.");
            }

            $result = ($handlers[$job->getName()])();
            $elapsedMs = round((hrtime(true) - $start) / 1e6, 3);
            $job->markDone($result, $elapsedMs);
            $this->logSuccess('job.done', ['id' => $job->getId(), 'name' => $job->getName(), 'elapsed_ms' => $elapsedMs]);

        } catch (\Throwable $e) {
            $job->markFailed($e->getMessage());
            $this->logError('job.failed', $e, ['id' => $job->getId(), 'name' => $job->getName()]);
        }

        $this->repo->save($job);
        return $job;
    }

    /** List all jobs (most recent first). */
    public function list(): array
    {
        return $this->repo->findAll();
    }

    /** Queue stats (total + by_status counts). */
    public function stats(): array
    {
        return $this->repo->stats();
    }
}

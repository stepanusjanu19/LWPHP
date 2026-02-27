<?php

namespace Kei\Lwphp\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Kei\Lwphp\Base\Repository as BaseRepository;
use Kei\Lwphp\Entity\Job;

/**
 * JobRepository — Background job queue data access.
 *
 * Extends Base\Repository for generic CRUD.
 * Adds queue-specific methods: push(), fetchNext(), stats(), cancel(), purgeOld().
 *
 * The atomic fetchNext() uses DBAL-level UPDATE to prevent double-claiming
 * in concurrent worker scenarios.
 */
class JobRepository extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    protected function entityClass(): string
    {
        return Job::class;
    }

    // ── Queue-specific CRUD ───────────────────────────────────────────────────

    public function push(string $name, array $payload = []): Job
    {
        $job = new Job($name, $payload);
        $this->save($job); // inherited from Base\Repository
        return $job;
    }

    /**
     * Atomically claim the next pending job.
     *
     * Uses DBAL-level UPDATE to prevent race conditions when multiple
     * workers are running simultaneously.
     */
    public function fetchNext(): ?Job
    {
        $conn = $this->getConnection();

        // Find oldest pending
        $row = $conn->fetchAssociative(
            'SELECT id FROM jobs WHERE status = ? ORDER BY created_at ASC LIMIT 1',
            ['pending']
        );
        if (!$row) {
            return null;
        }

        // Atomic claim
        $affected = $conn->executeStatement(
            'UPDATE jobs SET status = ?, attempts = attempts + 1 WHERE id = ? AND status = ?',
            ['processing', $row['id'], 'pending']
        );
        if ($affected === 0) {
            return null; // Another worker claimed it first
        }

        $this->clearIdentityMap();
        return $this->findById($row['id']);
    }

    /**
     * Cancel a pending job (DBAL-level to avoid detached entity issues).
     */
    public function cancel(int $id): bool
    {
        $conn = $this->getConnection();
        $affected = $conn->executeStatement(
            "UPDATE jobs SET status = 'failed', error = 'Cancelled by user' WHERE id = ? AND status = 'pending'",
            [$id]
        );
        if ($affected === 0) {
            return false;
        }
        $this->clearIdentityMap();
        return true;
    }

    /**
     * Queue statistics by status.
     */
    public function stats(): array
    {
        $all = $this->findAll();
        $byStatus = [];
        foreach ($all as $j) {
            $byStatus[$j->getStatus()] = ($byStatus[$j->getStatus()] ?? 0) + 1;
        }
        return ['total' => count($all), 'by_status' => $byStatus];
    }

    /**
     * Purge done/failed jobs.
     *
     * $days = 0  → purge ALL done/failed (default)
     * $days > 0  → purge only jobs older than N days
     */
    public function purgeOld(int $days = 0): int
    {
        $conn = $this->getConnection();

        if ($days === 0) {
            $removed = (int) $conn->executeStatement(
                "DELETE FROM jobs WHERE status IN ('done','failed')"
            );
        } else {
            $cutoff = (new \DateTimeImmutable("-{$days} days"))->format('Y-m-d H:i:s');
            $removed = (int) $conn->executeStatement(
                "DELETE FROM jobs WHERE status IN ('done','failed') AND created_at < ?",
                [$cutoff]
            );
        }

        $this->clearIdentityMap();
        return $removed;
    }
}

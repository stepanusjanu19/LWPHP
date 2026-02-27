<?php

namespace Kei\Lwphp\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Kei\Lwphp\Base\Repository as BaseRepository;
use Kei\Lwphp\Entity\Task;

/**
 * DoctrineTaskRepository — Doctrine-backed Task repository.
 *
 * Extends Base\Repository which handles all generic CRUD via EntityManager.
 * This class only defines domain-specific query methods.
 */
class DoctrineTaskRepository extends BaseRepository implements TaskRepositoryInterface
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em);
    }

    protected function entityClass(): string
    {
        return Task::class;
    }

    // ── Domain queries ────────────────────────────────────────────────────────

    /** @return Task[] */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status], ['id' => 'DESC']);
    }

    /** @return Task[] */
    public function findByPriority(int $priority): array
    {
        return $this->findBy(['priority' => $priority], ['id' => 'DESC']);
    }

    // ── Seeding (dev mode only) ───────────────────────────────────────────────

    /**
     * Populate demo tasks if the table is empty.
     * Called once on first boot in debug mode.
     */
    public function seed(): void
    {
        if (count($this->findAll()) > 0) {
            return;
        }

        $demos = [
            ['Build LWPHP API', 'Create a REST API with Doctrine ORM', 2],
            ['Write Unit Tests', 'PHPUnit tests for service layer', 1],
            ['Deploy to Staging', 'Configure CI/CD + Docker', 3],
        ];

        foreach ($demos as [$title, $desc, $priority]) {
            $task = new Task($title, $desc, $priority);
            $this->em->persist($task);
        }
        $this->em->flush();
    }
}

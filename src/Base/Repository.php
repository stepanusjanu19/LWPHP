<?php

namespace Kei\Lwphp\Base;

use Doctrine\ORM\EntityManagerInterface;
use Kei\Lwphp\Contract\RepositoryInterface;

/**
 * Abstract Doctrine Repository — SOLID base for all data access objects.
 *
 * Implements the full RepositoryInterface using Doctrine EntityManager.
 * Concrete repositories only need to implement entityClass() and any
 * domain-specific query methods.
 *
 * Follows the Open/Closed Principle: close for modification, open for extension.
 */
abstract class Repository implements RepositoryInterface
{
    public function __construct(
        protected readonly EntityManagerInterface $em,
    ) {
    }

    // ── Must override ─────────────────────────────────────────────────────────

    /**
     * Return the fully-qualified entity class name managed by this repository.
     * Example: return \Kei\Lwphp\Entity\Task::class;
     */
    abstract protected function entityClass(): string;

    // ── RepositoryInterface implementation ────────────────────────────────────

    public function findById(int|string $id): mixed
    {
        return $this->em->find($this->entityClass(), $id);
    }

    public function findAll(): array
    {
        return $this->em->getRepository($this->entityClass())->findAll();
    }

    public function save(mixed $entity): void
    {
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function delete(int|string $id): bool
    {
        $entity = $this->findById($id);
        if ($entity === null) {
            return false;
        }
        $this->em->remove($entity);
        $this->em->flush();
        return true;
    }

    public function count(): int
    {
        return (int) $this->em->getRepository($this->entityClass())->count([]);
    }

    // ── Convenience helpers ───────────────────────────────────────────────────

    /**
     * Find entities matching a criteria array (field => value pairs).
     *
     * @param array<string, mixed> $criteria
     * @param array<string, string>|null $orderBy  e.g. ['createdAt' => 'DESC']
     */
    protected function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): array
    {
        return $this->em->getRepository($this->entityClass())
            ->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Find a single entity matching criteria.
     *
     * @param array<string, mixed> $criteria
     */
    protected function findOneBy(array $criteria): mixed
    {
        return $this->em->getRepository($this->entityClass())->findOneBy($criteria);
    }

    /** Clear the Doctrine identity map (use after DBAL-level mutations). */
    protected function clearIdentityMap(): void
    {
        $this->em->clear();
    }

    /** Get the underlying DBAL connection for raw queries. */
    protected function getConnection(): \Doctrine\DBAL\Connection
    {
        return $this->em->getConnection();
    }
}

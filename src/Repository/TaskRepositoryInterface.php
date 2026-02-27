<?php

namespace Kei\Lwphp\Repository;

use Kei\Lwphp\Contract\RepositoryInterface;

/**
 * TaskRepositoryInterface
 *
 * Extends the generic RepositoryInterface for Task-specific queries.
 * findById() is inherited from RepositoryInterface with a mixed return type,
 * allowing both InMemoryTaskRepository (Domain\Task) and
 * DoctrineTaskRepository (Entity\Task) to satisfy this contract.
 *
 * @extends RepositoryInterface<\Kei\Lwphp\Entity\Task>
 */
interface TaskRepositoryInterface extends RepositoryInterface
{
    /** @return \Kei\Lwphp\Entity\Task[] */
    public function findByStatus(string $status): array;

    /** @return \Kei\Lwphp\Entity\Task[] */
    public function findByPriority(int $priority): array;

    // findById(int|string $id): mixed — inherited from RepositoryInterface
}

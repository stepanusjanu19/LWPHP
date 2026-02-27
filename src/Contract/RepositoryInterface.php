<?php

namespace Kei\Lwphp\Contract;

/**
 * Generic repository contract.
 * @template T
 */
interface RepositoryInterface
{
    /** @return T|null */
    public function findById(int|string $id): mixed;

    /** @return T[] */
    public function findAll(): array;

    /** @param T $entity */
    public function save(mixed $entity): void;

    public function delete(int|string $id): bool;

    public function count(): int;
}

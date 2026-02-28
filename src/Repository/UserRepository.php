<?php

namespace Kei\Lwphp\Repository;

use Kei\Lwphp\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class UserRepository
{
    private \Doctrine\ORM\EntityRepository $repository;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->repository = $this->em->getRepository(User::class);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->repository->findOneBy(['username' => $username]);
    }

    public function findByEmail(string $email): ?User
    {
        return $this->repository->findOneBy(['email' => $email]);
    }

    public function save(User $user): void
    {
        $this->em->persist($user);
        $this->em->flush();
    }

    public function count(): int
    {
        return $this->repository->count([]);
    }
}
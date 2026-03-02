<?php

namespace Kei\Lwphp\Database\Seeders;

use Kei\Lwphp\Entity\User;
use Kei\Lwphp\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class AdminSeeder implements SeederInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepository
    ) {
    }

    public function run(): void
    {
        $username = 'admin';
        $email = 'admin@example.com';
        $password = 'password';

        if ($this->userRepository->findByUsername($username)) {
            echo "=> Admin user already exists.\n";
            return;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
        $user->setRole('admin');

        $this->userRepository->save($user);

        echo "=> Successfully seeded admin user (admin / password).\n";
    }
}

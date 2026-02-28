<?php

namespace Kei\Lwphp\Console;

use Kei\Lwphp\Entity\User;
use Kei\Lwphp\Repository\UserRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

class SeedAdminCommand extends Command
{
    protected static $defaultName = 'db:seed:admin';
    protected static $defaultDescription = 'Seeds the default admin user into the database';

    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository
        )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:seed:admin')
            ->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = 'admin';
        $email = 'admin@example.com';
        $password = 'password';

        if ($this->userRepository->findByUsername($username)) {
            $output->writeln("<comment>Admin user already exists!</comment>");
            return Command::SUCCESS;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);
        $user->setPasswordHash(password_hash($password, PASSWORD_DEFAULT));
        $user->setRole('admin');

        $this->userRepository->save($user);

        $output->writeln("<info>Successfully seeded admin user.</info>");
        $output->writeln("Username: <comment>{$username}</comment>");
        $output->writeln("Password: <comment>{$password}</comment>");

        return Command::SUCCESS;
    }
}
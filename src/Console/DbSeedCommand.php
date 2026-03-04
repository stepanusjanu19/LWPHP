<?php

namespace Kei\Lwphp\Console;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DbSeedCommand extends Command
{
    protected static $defaultName = 'db:seed';
    protected static $defaultDescription = 'Run all database seeders';

    public function __construct(
        private readonly ContainerInterface $container
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:seed')
            ->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $seederDir = __DIR__ . '/../Database/Seeders';
        if (!is_dir($seederDir)) {
            $output->writeln('<info>No seeders directory found.</info>');
            return self::SUCCESS;
        }

        $files = glob($seederDir . '/*Seeder.php');
        if (empty($files)) {
            $output->writeln('<info>No seeders found to run.</info>');
            return self::SUCCESS;
        }

        $output->writeln('<comment>Running database seeders...</comment>');

        foreach ($files as $file) {
            $className = basename($file, '.php');
            $fqcn = "\\Kei\\Lwphp\\Database\\Seeders\\{$className}";

            if (class_exists($fqcn) && is_subclass_of($fqcn, \Kei\Lwphp\Database\Seeders\SeederInterface::class)) {
                $output->writeln("Running seeder: <info>{$className}</info>");

                try {
                    // Resolve via DI to inject EntityManager, Repositories, etc.
                    $seeder = $this->container->get($fqcn);
                    $seeder->run();
                } catch (\Exception $e) {
                    $output->writeln("<error>Error running $className: {$e->getMessage()}</error>");
                    return self::FAILURE;
                }
            }
        }

        $output->writeln('<info>Database seeding completed successfully!</info>');

        return self::SUCCESS;
    }
}

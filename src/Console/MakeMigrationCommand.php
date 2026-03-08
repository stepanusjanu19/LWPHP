<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeMigrationCommand extends Command
{
    protected static $defaultName = 'make:migration';

    protected function configure(): void
    {
        $this->setName('make:migration')
            ->setDescription('Create a new database migration file')
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the migration (e.g. CreateUsersTable)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $input->getArgument('name');

        // Ensure PascalCase
        $className = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));

        $migrationsDir = __DIR__ . '/../../database/migrations';
        if (!is_dir($migrationsDir)) {
            mkdir($migrationsDir, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $fileName = "{$timestamp}_{$className}.php";
        $filePath = "{$migrationsDir}/{$fileName}";

        $stubPath = __DIR__ . '/../../stubs/migration.stub';
        if (!file_exists($stubPath)) {
            $output->writeln("<error>Stub not found at {$stubPath}</error>");
            return Command::FAILURE;
        }

        $content = file_get_contents($stubPath);
        $content = str_replace('{{class}}', $className, $content);

        file_put_contents($filePath, $content);

        $output->writeln("<info>Migration created successfully:</info> {$filePath}");

        return Command::SUCCESS;
    }
}

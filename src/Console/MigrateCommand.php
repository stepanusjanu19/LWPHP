<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManagerInterface;

class MigrateCommand extends Command
{
    protected static $defaultName = 'db:migrate';
    protected static $defaultDescription = 'Syncs the database schema with the ORM metadata';

    public function __construct(
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('db:migrate')
            ->setDescription('Run the database migrations');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $conn = $this->em->getConnection();
        $schema = $conn->createSchemaManager();

        // 1. Create migrations tracking table if it doesn't exist
        if (!$schema->tablesExist(['migrations'])) {
            $table = new \Doctrine\DBAL\Schema\Table('migrations');
            $table->addColumn('id', 'integer', ['autoincrement' => true]);
            $table->addColumn('migration', 'string', ['length' => 255]);
            $table->addColumn('executed_at', 'datetime', ['default' => 'CURRENT_TIMESTAMP']);
            $table->setPrimaryKey(['id']);
            $schema->createTable($table);
            $output->writeln('<comment>Created migrations tracking table.</comment>');
        }

        // 2. Scan migration files
        $migrationsDir = __DIR__ . '/../../database/migrations';
        if (!is_dir($migrationsDir)) {
            $output->writeln('<info>No migrations directory found.</info>');
            return self::SUCCESS;
        }

        $files = glob($migrationsDir . '/*.php');
        sort($files);

        // 3. Get already executed migrations
        $executed = $conn->executeQuery('SELECT migration FROM migrations')->fetchFirstColumn();

        $ranAny = false;

        // 4. Run new migrations
        foreach ($files as $file) {
            $filename = basename($file);
            if (in_array($filename, $executed, true)) {
                continue; // Already ran
            }

            // Extract class name from filename like 2026_03_07_123456_CreateUsersTable.php
            preg_match('/^\d{4}_\d{2}_\d{2}_\d{6}_(.+)\.php$/', $filename, $matches);
            $className = $matches[1] ?? null;

            if (!$className) {
                $output->writeln("<error>Invalid migration filename format: {$filename}</error>");
                continue;
            }

            require_once $file;

            if (!class_exists($className)) {
                $output->writeln("<error>Migration class {$className} not found in {$file}</error>");
                continue;
            }

            $output->writeln("<comment>Migrating:</comment> {$filename}");

            $migration = new $className();
            $migration->up($conn);

            // Record execution
            $conn->insert('migrations', [
                'migration' => $filename,
                'executed_at' => date('Y-m-d H:i:s')
            ]);

            $output->writeln("<info>Migrated:</info>  {$filename}");
            $ranAny = true;
        }

        if (!$ranAny) {
            $output->writeln('<info>Nothing to migrate.</info>');
        }

        return self::SUCCESS;
    }
}

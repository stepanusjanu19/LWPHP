<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

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
            ->setDescription('Syncs the database schema with the ORM metadata');
        $this->addOption('dump-sql', null, InputOption::VALUE_NONE, 'Dump the generated SQL instead of executing it');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dumpSql = $input->getOption('dump-sql');
        $classes = $this->em->getMetadataFactory()->getAllMetadata();

        if (empty($classes)) {
            $output->writeln('<info>No mapped entities found.</info>');
            return self::SUCCESS;
        }

        $tool = new SchemaTool($this->em);

        if ($dumpSql) {
            $sql = $tool->getUpdateSchemaSql($classes);
            if (empty($sql)) {
                $output->writeln('<info>Nothing to update - database is in sync.</info>');
            } else {
                $output->writeln(implode(";\n", $sql) . ';');
            }
            return self::SUCCESS;
        }

        $output->writeln('<comment>Updating database schema...</comment>');
        $tool->updateSchema($classes);
        $output->writeln('<info>Database schema updated successfully!</info>');

        return self::SUCCESS;
    }
}

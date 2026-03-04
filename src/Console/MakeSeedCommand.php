<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeSeedCommand extends MakeCommand
{
    protected static $defaultName = 'make:seed';
    protected static $defaultDescription = 'Creates a new Database Seeder class';

    protected function configure(): void
    {
        $this->setName('make:seed')
            ->setDescription(self::$defaultDescription);
        $this->addArgument('name', InputArgument::REQUIRED, 'The base name of the seeder (e.g., User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));

        $content = $this->renderStub('seeder', [
            'className' => $name,
        ]);

        $path = base_path('src/Database/Seeders/' . $name . 'Seeder.php');
        $this->writeClass($path, $content, $output);

        return self::SUCCESS;
    }
}

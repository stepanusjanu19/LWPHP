<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeEntityCommand extends MakeCommand
{
    protected static $defaultName = 'make:entity';
    protected static $defaultDescription = 'Creates a new Entity class';

    protected function configure(): void
    {
        $this->setName('make:entity')
            ->setDescription('Creates a new Entity class');
        $this->addArgument('name', InputArgument::REQUIRED, 'The name of the entity class (e.g., Article)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));
        $tableName = $this->toSnakeCase($name) . 's'; // simple pluralization
        
        $content = $this->renderStub('entity', [
            'className' => $name,
            'tableName' => $tableName
        ]);

        $path = base_path('src/Entity/' . $name . '.php');
        $this->writeClass($path, $content, $output);

        return self::SUCCESS;
    }
}

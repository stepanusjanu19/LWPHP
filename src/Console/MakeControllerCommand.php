<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeControllerCommand extends MakeCommand
{
    protected static $defaultName = 'make:controller';
    protected static $defaultDescription = 'Creates a new Controller class';

    protected function configure(): void
    {
        $this->setName('make:controller')
            ->setDescription('Creates a new Controller class');
        $this->addArgument('name', InputArgument::REQUIRED, 'The base name of the entity (e.g., Article)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));
        
        $content = $this->renderStub('controller', [
            'className' => $name,
            'resourceNameLower' => $this->toSnakeCase($name),
        ]);

        $path = base_path('src/Controller/' . $name . 'Controller.php');
        $this->writeClass($path, $content, $output);

        return self::SUCCESS;
    }
}

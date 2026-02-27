<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeServiceCommand extends MakeCommand
{
    protected static $defaultName = 'make:service';
    protected static $defaultDescription = 'Creates a new Service class';

    protected function configure(): void
    {
        $this->setName('make:service')
            ->setDescription('Creates a new Service class');
        $this->addArgument('name', InputArgument::REQUIRED, 'The base name of the entity (e.g., Article)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));
        
        $content = $this->renderStub('service', [
            'className' => $name,
        ]);

        $path = base_path('src/Service/' . $name . 'Service.php');
        $this->writeClass($path, $content, $output);

        return self::SUCCESS;
    }
}

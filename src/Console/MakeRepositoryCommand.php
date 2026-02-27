<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeRepositoryCommand extends MakeCommand
{
    protected static $defaultName = 'make:repository';
    protected static $defaultDescription = 'Creates a new Repository class';

    protected function configure(): void
    {
        $this->setName('make:repository')
            ->setDescription('Creates a new Repository class');
        $this->addArgument('name', InputArgument::REQUIRED, 'The base name of the entity (e.g., Article)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));
        
        $content = $this->renderStub('repository', [
            'className' => $name,
        ]);

        $path = base_path('src/Repository/' . $name . 'Repository.php');
        $this->writeClass($path, $content, $output);

        return self::SUCCESS;
    }
}

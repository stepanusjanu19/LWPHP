<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeFactoryCommand extends MakeCommand
{
    protected static $defaultName = 'make:factory';
    protected static $defaultDescription = 'Creates a new Database Factory class';

    protected function configure(): void
    {
        $this->setName('make:factory')
            ->setDescription(self::$defaultDescription);
        $this->addArgument('name', InputArgument::REQUIRED, 'The base name of the entity factory (e.g., User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));

        $content = $this->renderStub('factory', [
            'className' => $name,
        ]);

        $path = base_path('src/Database/Factories/' . $name . 'Factory.php');
        $this->writeClass($path, $content, $output);

        return self::SUCCESS;
    }
}

<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to rapidly scaffold a new Domain-Driven Design (DDD) module.
 * It generates an Entity, Repository, Service, and Controller set out of the box,
 * pre-wired with DI and Base classes.
 */
class MakeModuleCommand extends Command
{
    protected static $defaultName = 'make:module';

    protected function configure(): void
    {
        $this->setName('make:module')
            ->setDescription('Scaffold a complete DDD module (Entity, Repository, Service, Controller)')
            ->addArgument('name', InputArgument::REQUIRED, 'The base name of the module (e.g. User)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = ucfirst($input->getArgument('name'));
        $tableName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name)) . 's';

        $stubs = [
            'Entity' => [
                'stub' => __DIR__ . '/../../stubs/entity.stub',
                'target' => __DIR__ . '/../Entity/' . $name . '.php',
            ],
            'Repository' => [
                'stub' => __DIR__ . '/../../stubs/repository.stub',
                'target' => __DIR__ . '/../Repository/' . $name . 'Repository.php',
            ],
            'Service' => [
                'stub' => __DIR__ . '/../../stubs/service.stub',
                'target' => __DIR__ . '/../Service/' . $name . 'Service.php',
            ],
            'Controller' => [
                'stub' => __DIR__ . '/../../stubs/controller.stub',
                'target' => __DIR__ . '/../Controller/' . $name . 'Controller.php',
            ],
        ];

        foreach ($stubs as $type => $paths) {
            if (!file_exists($paths['stub'])) {
                $output->writeln("<error>Stub for {$type} not found at {$paths['stub']}</error>");
                continue;
            }

            if (file_exists($paths['target'])) {
                $output->writeln("<comment>{$type} already exists: {$paths['target']}</comment>");
                continue;
            }

            $content = file_get_contents($paths['stub']);
            $content = str_replace(['{{class}}', '{{tableName}}'], [$name, $tableName], $content);

            if (!is_dir(dirname($paths['target']))) {
                mkdir(dirname($paths['target']), 0755, true);
            }

            file_put_contents($paths['target'], $content);
            $output->writeln("<info>Created {$type}: {$paths['target']}</info>");
        }

        // Output helpful instructions for DI configuration
        $output->writeln("");
        $output->writeln("<info>Module {$name} generated successfully!</info>");
        $output->writeln("<comment>Don't forget to register {$name}Controller in config/di/definitions.php if you want to use it:</comment>");
        $output->writeln("  <info>\\Kei\\Lwphp\\Controller\\{$name}Controller::class => \\DI\\autowire(\\Kei\\Lwphp\\Controller\\{$name}Controller::class),</info>");

        return Command::SUCCESS;
    }
}

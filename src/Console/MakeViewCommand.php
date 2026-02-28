<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeViewCommand extends MakeCommand
{
    protected static $defaultName = 'make:view';
    protected static $defaultDescription = 'Creates HTMX-powered Twig view templates for a resource';

    protected function configure(): void
    {
        $this->setName('make:view')
            ->setDescription(self::$defaultDescription)
            ->addArgument('name', InputArgument::REQUIRED, 'The base name (e.g., Article)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));
        $resourceNameLower = $this->toSnakeCase($name);

        $viewDirPath = base_path("resources/views/{$resourceNameLower}s");

        if (!is_dir($viewDirPath)) {
            mkdir($viewDirPath, 0755, true);
        }

        // Generate Index View
        $indexPath = $viewDirPath . '/index.twig';
        if (!file_exists($indexPath)) {
            $indexStub = file_get_contents(__DIR__ . '/Stubs/view_index.stub.twig');
            $indexContent = str_replace(
            ['{{ className }}', '{{ resourceNameLower }}'],
            [$name, $resourceNameLower],
                $indexStub
            );
            file_put_contents($indexPath, $indexContent);
            $output->writeln("<info>Created:</info> {$indexPath}");
        }
        else {
            $output->writeln("<comment>Skipped:</comment> File already exists -> {$indexPath}");
        }

        // Generate Feed View
        $feedPath = $viewDirPath . '/feed.twig';
        if (!file_exists($feedPath)) {
            $feedStub = file_get_contents(__DIR__ . '/Stubs/view_feed.stub.twig');
            $feedContent = str_replace(
            ['{{ className }}', '{{ resourceNameLower }}'],
            [$name, $resourceNameLower],
                $feedStub
            );
            file_put_contents($feedPath, $feedContent);
            $output->writeln("<info>Created:</info> {$feedPath}");
        }
        else {
            $output->writeln("<comment>Skipped:</comment> File already exists -> {$feedPath}");
        }

        // Generate Form View
        $formPath = $viewDirPath . '/form.twig';
        if (!file_exists($formPath)) {
            $formStub = file_get_contents(__DIR__ . '/Stubs/view_form.stub.twig');
            $formContent = str_replace(
            ['{{ className }}', '{{ resourceNameLower }}'],
            [$name, $resourceNameLower],
                $formStub
            );
            file_put_contents($formPath, $formContent);
            $output->writeln("<info>Created:</info> {$formPath}");
        }
        else {
            $output->writeln("<comment>Skipped:</comment> File already exists -> {$formPath}");
        }

        return self::SUCCESS;
    }
}
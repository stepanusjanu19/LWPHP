<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeCrudCommand extends MakeCommand
{
    protected static $defaultName = 'make:crud';
    protected static $defaultDescription = 'Scaffolds an Entity, Repository, Service, and Controller';

    protected function configure(): void
    {
        $this->setName('make:crud')
            ->setDescription('Scaffolds an Entity, Repository, Service, and Controller');
        $this->addArgument('name', InputArgument::REQUIRED, 'The base name for the resources (e.g., Article)');
    }


    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name'));
        $app = $this->getApplication();

        $output->writeln("<info>Scaffolding CRUD for {$name}...</info>");

        // 1. Entity
        $app->find('make:entity')->run(new ArrayInput(['name' => $name]), $output);

        // 2. Repository
        $app->find('make:repository')->run(new ArrayInput(['name' => $name]), $output);

        // 3. Service
        $app->find('make:service')->run(new ArrayInput(['name' => $name]), $output);

        // 4. Controller
        $app->find('make:controller')->run(new ArrayInput(['name' => $name]), $output);

        // 5. Views
        $app->find('make:view')->run(new ArrayInput(['name' => $name]), $output);

        $this->autoWireDependencies($name, $output);
        $this->autoWireRoutes($name, $output);

        $output->writeln("<info>Done!</info> Run <comment>php bin/lwphp db:migrate</comment> to sync the database.");

        return self::SUCCESS;
    }

    private function autoWireDependencies(string $name, OutputInterface $output): void
    {
        $diPath = base_path('config/di/definitions.php');
        if (!file_exists($diPath))
            return;

        $content = file_get_contents($diPath);

        // Primitive check if already wired
        if (str_contains($content, "{$name}Controller::class")) {
            $output->writeln("<comment>Info:</comment> Component {$name} already wired in DI definitions.");
            return;
        }

        // We will insert right before the Controller layer section comment or end of array
        $serviceWire = "\n  \\Kei\\Lwphp\\Service\\{$name}Service::class => \\DI\\autowire(\\Kei\\Lwphp\\Service\\{$name}Service::class),\n";

        $controllerWire = "\n  \\Kei\\Lwphp\\Controller\\{$name}Controller::class => \\DI\\autowire(\\Kei\\Lwphp\\Controller\\{$name}Controller::class),\n";

        // Simple string injection before the end of the array `];`
        $newContent = str_replace("\n];", $serviceWire . $controllerWire . "\n];", $content);

        file_put_contents($diPath, $newContent);
        $output->writeln("<info>Auto-wired:</info> Added {$name} to config/di/definitions.php");
    }

    private function autoWireRoutes(string $name, OutputInterface $output): void
    {
        $routePath = base_path('src/Routing/Routes.php');
        if (!file_exists($routePath))
            return;

        $content = file_get_contents($routePath);
        $slug = $this->toSnakeCase($name) . 's'; // resource URL slug pluralized

        if (str_contains($content, "'/{$slug}'")) {
            $output->writeln("<comment>Info:</comment> Routes for /{$slug} already exist.");
            return;
        }

        // Add Use statement if missing
        if (!str_contains($content, "use Kei\\Lwphp\\Controller\\{$name}Controller;")) {
            $content = preg_replace('/(use Kei\\\\Lwphp\\\\Controller\\\\.*?;)\n/m', "$1\nuse Kei\\Lwphp\\Controller\\{$name}Controller;\n", $content, 1);
        }

        $routes = <<<PHP

        // ── {$name} UI ──────────────────────────────────────────────────────
        \$r->get('/{$slug}',                           [{$name}Controller::class, 'index']);
        \$r->get('/{$slug}/feed',                      [{$name}Controller::class, 'feed']);
        \$r->get('/{$slug}/create',                    [{$name}Controller::class, 'create']);
        \$r->get('/{$slug}/{id:\d+}/edit',             [{$name}Controller::class, 'edit']);
        \$r->post('/{$slug}',                          [{$name}Controller::class, 'store']);
        \$r->put('/{$slug}/{id:\d+}',                 [{$name}Controller::class, 'update']);
        \$r->delete('/{$slug}/{id:\d+}',              [{$name}Controller::class, 'destroy']);
PHP;

        // Insert before Benchmark API or end of method
        if (str_contains($content, '// ── Benchmark API')) {
            $content = str_replace('// ── Benchmark API', ltrim($routes) . "\n\n        // ── Benchmark API", $content);
        }
        else {
            $content = str_replace("\n    }\n}", ltrim($routes) . "\n    }\n}", $content);
        }

        file_put_contents($routePath, $content);
        $output->writeln("<info>Auto-wired:</info> Added /{$slug} to src/Routing/routes.php");
    }
}
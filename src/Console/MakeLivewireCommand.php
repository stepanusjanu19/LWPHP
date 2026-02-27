<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MakeLivewireCommand extends MakeCommand
{
    protected static $defaultName = 'make:livewire';
    protected static $defaultDescription = 'Creates a Livewire-style Component and Twig view';

    protected function configure(): void
    {
        $this->setName('make:livewire')
            ->setDescription(self::$defaultDescription)
            ->addArgument('name', InputArgument::REQUIRED, 'The name of the component (e.g., Counter)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $name = $this->toPascalCase($input->getArgument('name')) . 'Component';
        $viewName = $this->toSnakeCase(str_replace('Component', '', $name));

        // 1. Generate PHP Component
        $componentDir = base_path('src/Livewire');
        if (!is_dir($componentDir)) {
            mkdir($componentDir, 0755, true);
        }

        $componentPath = $componentDir . '/' . $name . '.php';
        if (!file_exists($componentPath)) {
            $stub = <<<PHP
<?php

namespace Kei\Lwphp\Livewire;

class {$name} extends Component
{
    public function render(): string
    {
        return 'livewire/{$viewName}.twig';
    }
}
PHP;
            file_put_contents($componentPath, $stub);
            $output->writeln("<info>Created Component:</info> {$componentPath}");
        } else {
            $output->writeln("<comment>Skipped Component:</comment> Already exists.");
        }

        // 2. Generate Base Component if missing
        $baseComponentPath = $componentDir . '/Component.php';
        if (!file_exists($baseComponentPath)) {
            $baseStub = <<<PHP
<?php

namespace Kei\Lwphp\Livewire;

abstract class Component
{
    public string \$id;

    public function __construct()
    {
        \$this->id = bin2hex(random_bytes(10));
    }

    abstract public function render(): string;

    /**
     * Extracts public properties to pass to Twig
     */
    public function dehydrate(): array
    {
        \$props = [];
        \$reflection = new \ReflectionClass(\$this);
        foreach (\$reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as \$property) {
            if (\$property->getName() !== 'id') {
                \$props[\$property->getName()] = \$property->getValue(\$this);
            }
        }
        return \$props;
    }

    /**
     * Re-hydrates state into public properties
     */
    public function hydrate(array \$data): void
    {
        foreach (\$data as \$key => \$val) {
            if (property_exists(\$this, \$key)) {
                \$this->{\$key} = \$val;
            }
        }
    }
}
PHP;
            file_put_contents($baseComponentPath, $baseStub);
            $output->writeln("<info>Created Base Component:</info> {$baseComponentPath}");
        }

        // 3. Generate Twig View
        $viewDir = base_path('resources/views/livewire');
        if (!is_dir($viewDir)) {
            mkdir($viewDir, 0755, true);
        }

        $viewPath = $viewDir . '/' . $viewName . '.twig';
        if (!file_exists($viewPath)) {
            $viewStub = <<<HTML
<div id="lw-{{ _id }}" hx-post="/livewire/message/{{ _name }}" hx-target="#lw-{{ _id }}" hx-swap="outerHTML">
    <!-- Component Content -->
    <h3>{$name}</h3>
</div>
HTML;
            file_put_contents($viewPath, $viewStub);
            $output->writeln("<info>Created View:</info> {$viewPath}");
        } else {
            $output->writeln("<comment>Skipped View:</comment> Already exists.");
        }

        return self::SUCCESS;
    }
}

<?php

namespace Kei\Lwphp\Service;

use Kei\Lwphp\Console\MakeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Application;
use Kei\Lwphp\Console\MakeCrudCommand;
use Kei\Lwphp\Console\MakeEntityCommand;
use Kei\Lwphp\Console\MakeRepositoryCommand;
use Kei\Lwphp\Console\MakeServiceCommand;
use Kei\Lwphp\Console\MakeControllerCommand;
use Kei\Lwphp\Console\MakeViewCommand;
use Kei\Lwphp\Console\MakeLivewireCommand;

class GeneratorService
{
    /**
     * Generates a CRUD resource.
     */
    public function generateCrud(string $name): array
    {
        $app = new Application();
        $app->add(new MakeCrudCommand());
        $app->add(new MakeEntityCommand());
        $app->add(new MakeRepositoryCommand());
        $app->add(new MakeServiceCommand());
        $app->add(new MakeControllerCommand());
        $app->add(new MakeViewCommand());

        $command = $app->find('make:crud');
        $output = new BufferedOutput();

        $input = new ArrayInput(['name' => $name]);
        $exitCode = $command->run($input, $output);

        return [
            'success' => $exitCode === 0,
            'message' => $output->fetch(),
            'name' => $name
        ];
    }

    /**
     * Generates a Livewire component.
     */
    public function generateLivewire(string $name): array
    {
        $app = new Application();
        $app->add(new MakeLivewireCommand());
        $app->add(new MakeViewCommand()); // If make:livewire depends on make:view

        $command = $app->find('make:livewire');
        $output = new BufferedOutput();

        $input = new ArrayInput(['name' => $name]);
        $exitCode = $command->run($input, $output);

        return [
            'success' => $exitCode === 0,
            'message' => $output->fetch(),
            'name' => $name
        ];
    }
}

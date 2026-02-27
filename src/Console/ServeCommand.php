<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ServeCommand extends Command
{
    protected static $defaultName = 'serve';

    protected function configure(): void
    {
        $this->setName('serve')
            ->setDescription('Start the local development server')
            ->addOption('host', null, InputOption::VALUE_OPTIONAL, 'The host address to serve the application on', '127.0.0.1')
            ->addOption('port', 'p', InputOption::VALUE_OPTIONAL, 'The port to serve the application on', 8000);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $host = $input->getOption('host');
        $port = $input->getOption('port');
        
        $publicDir = base_path('public');
        
        $output->writeln("<info>Starting LWPHP development server...</info>");
        $output->writeln("Listening on <comment>http://{$host}:{$port}</comment>");
        $output->writeln("Document root is <comment>{$publicDir}</comment>");
        $output->writeln("Press Ctrl-C to quit.");

        $command = sprintf('php -S %s:%s -t %s', escapeshellarg($host), escapeshellarg($port), escapeshellarg($publicDir));
        
        // Use passthru to pipe output directly to the current console
        passthru($command, $status);

        return $status;
    }
}

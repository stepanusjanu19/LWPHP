<?php

namespace Kei\Lwphp\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class KeyGenerateCommand extends Command
{
    protected static $defaultName = 'key:generate';
    protected static $defaultDescription = 'Generates a secure application key and updates the .env file';

    protected function configure(): void
    {
        $this->setName('key:generate')
            ->setDescription(self::$defaultDescription);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = 'base64:' . base64_encode(random_bytes(32)); // 256-bit key

        $envPath = base_path('.env');

        if (!file_exists($envPath)) {
            $output->writeln("<error>The .env file does not exist. Please copy .env.example to .env first.</error>");
            return Command::FAILURE;
        }

        $content = file_get_contents($envPath);

        // Replace existing key or append if missing
        if (str_contains($content, 'APP_KEY=')) {
            $content = preg_replace('/^APP_KEY=.*$/m', 'APP_KEY=' . $key, $content);
        } else {
            $content .= "\nAPP_KEY=" . $key . "\n";
        }

        file_put_contents($envPath, $content);

        $output->writeln("<info>Application key set successfully.</info>");
        $output->writeln("<comment>Key:</comment> {$key}");

        return Command::SUCCESS;
    }
}

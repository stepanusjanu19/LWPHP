<?php

namespace Kei\Lwphp\Core;

use Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;

class ConfigLoader
{
    private array $config = [];
    private string $configDir;
    private string $env;

    public function __construct(string $configDir = __DIR__ . '/../../config')
    {
        $this->configDir = $configDir;
    }

    private function loadEnvironment(): void
    {
        $dotenv = Dotenv::createImmutable($this->configDir);
        $dotenv->load();

        $this->env = $_ENV['APP_ENV'] ?? 'development';

        if(file_exists($this->configDir . "/{$this->env}.env")){
            $dotenv->load($this->configDir . "/{$this->env}.env");
        }
    }

    private function loadAllConfigs(): void
    {
        $formats = ['php', 'json', 'ini', 'yaml', 'env'];
        foreach ($formats as $format){
            $this->loadConfigFormat($format);
        }
        $this->loadEnvironmentOverrides();
    }

    private function loadConfigFormat(string $format): void
    {
        $dir = $this->configDir . '/' . $format;
        if(!is_dir($dir)){
            return;
        }

        $files = glob($dir . '/*.{' . $format . '}', GLOB_BRACE);
        foreach ($files as $file){
            $filename = basename($file, '.' . $format);
            $this->config[$filename] = $this->parseConfigFile($file, $format);
        }
    }

    private function parseConfigFile(string $file, string $format): array
    {
        $content = file_get_contents($file);
        if($content === false){
            return [];
        }

        return match($format){
            'php' => $this->parsePhpConfig($content),
            'json' => $this->parseJsonConfig($content),
            'ini' => $this->parseIniConfig($content),
            'yaml' => $this->parseYamlConfig($content),
            'env' => $this->parseEnvConfig($content),
            'default' => []
        };
    }

    private function parsePhpConfig(string $content): array
    {
        $config = [];
        $tempFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tempFile, '<?php return ' . $content . ';');
        $config = include $tempFile;
        unlink($tempFile);
        return (array) $config;
    }

    private function parseJsonConfig(string $content): array
    {
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON in config: ' . json_last_error_msg());
        }
        return $decoded ?: [];
    }

    private function parseIniConfig(string $content): array
    {
        $config = parse_ini_string($content, true, INI_SCANNER_TYPED);
        return $config ?: [];
    }

    private function parseYamlConfig(string $content): array
    {
        return Yaml::parse($content) ?: [];
    }

    private function parseEnvConfig(string $content): array
    {
        $lines = explode("\n", $content);
        $config = [];

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
                continue;
            }

            if (preg_match('/^([^=]+)=(.*)$/', $line, $matches)) {
                $key = trim($matches[1]);
                $value = trim($matches[2], '"\'');
                $config[$key] = $value;
            }
        }

        return $config;
    }


    public function loadEnvironmentOverrides(): void
    {
        $envConfigDir = $this->configDir . '/' . $this->env;
        if(is_dir($envConfigDir))
            $envFiles = glob($envConfigDir . '/*.{php,json,ini,yaml,env}', GLOB_BRACE);

            foreach($envFiles as $file){
                $filename = basename($file);
                $format = pathinfo($file, PATHINFO_EXTENSION);
                $config = $this->parseConfigFile($file, $format);

                if(isset($this->config[$filename])){
                    $this->config[$filename] = array_replace_recursive(
                        $this->config[$filename],
                        $config
                    );
                }else{
                    $this->config[$filename] = $config;
                }
            }
    }
    public function get(string $key, $default = null){
        $keys = explode('.', $key);
        $current = $this->config;
        foreach ($keys as $k)
        {
            if(is_array($current) && array_key_exists($k, $current)){
                $current = $current[$k];
            }else{
                return $default;
            }
        }
        return $current;
    }
    public function all(): array
    {
        return $this->config;
    }
    public function getEnvironment(): string
    {
        return $this->env;
    }
    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }
}
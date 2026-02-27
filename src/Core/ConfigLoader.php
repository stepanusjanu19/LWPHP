<?php

namespace Kei\Lwphp\Core;

use Dotenv\Dotenv;
use Symfony\Component\Yaml\Yaml;

class ConfigLoader
{
    private array $config = [];
    private array $keyCache = [];   // dot-key → resolved value cache
    private string $configDir;
    private string $env = 'development';
    private string $cacheFile;

    public function __construct(string $configDir = __DIR__ . '/../../config')
    {
        $this->configDir = rtrim($configDir, '/\\');
        $this->cacheFile = sys_get_temp_dir() . '/lwphp_config_' . md5($this->configDir) . '.php';

        $helpersFile = $this->configDir . '/helpers.php';
        if (file_exists($helpersFile)) {
            require_once $helpersFile;
        }

        $this->loadEnvironment();

        if (!$this->loadFromCache()) {
            $this->loadAllConfigs();
            $this->saveToCache();
        }
    }

    // -------------------------------------------------------------------------
    // Cache
    // -------------------------------------------------------------------------

    /**
     * Try to load serialized config from disk cache.
     * Cache is invalidated when any config file is newer than the cache file.
     */
    private function loadFromCache(): bool
    {
        if (!file_exists($this->cacheFile)) {
            return false;
        }

        $cacheTime = filemtime($this->cacheFile);

        // Scan for config files newer than cache
        $files = glob($this->configDir . '/*.{php,json,ini,yaml,yml}', GLOB_BRACE) ?: [];
        foreach ($files as $file) {
            if (filemtime($file) >= $cacheTime) {
                return false;  // stale
            }
        }

        $data = @include $this->cacheFile;
        if (!is_array($data)) {
            return false;
        }

        $this->config = $data;
        return true;
    }

    private function saveToCache(): void
    {
        $export = "<?php\nreturn " . var_export($this->config, true) . ";\n";
        @file_put_contents($this->cacheFile, $export, LOCK_EX);
    }

    /** Force invalidate the config cache (call after deploy) */
    public function clearCache(): void
    {
        if (file_exists($this->cacheFile)) {
            @unlink($this->cacheFile);
        }
        $this->keyCache = [];
    }

    // -------------------------------------------------------------------------
    // Environment
    // -------------------------------------------------------------------------

    private function loadEnvironment(): void
    {
        // Standard convention: .env lives at project root (one level up from config/)
        $projectRoot = dirname($this->configDir);
        $dotenvDirs = [$projectRoot, $this->configDir]; // check root first

        foreach ($dotenvDirs as $dir) {
            if (file_exists($dir . '/.env')) {
                $dotenv = Dotenv::createImmutable($dir);
                $dotenv->safeLoad();
                break; // stop at first .env found
            }
        }

        $this->env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'development';

        // Load environment-specific overrides (.env.development, .env.production, etc.)
        foreach ($dotenvDirs as $dir) {
            $envFile = $dir . '/.env.' . $this->env;
            if (file_exists($envFile)) {
                $dotenv = Dotenv::createImmutable($dir, '.env.' . $this->env);
                $dotenv->safeLoad();
                $this->env = $_ENV['APP_ENV'] ?? $this->env;
                break;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Config loading
    // -------------------------------------------------------------------------

    private function loadAllConfigs(): void
    {
        $this->loadConfigFromDir($this->configDir);
        $this->loadEnvironmentOverrides();
    }

    private function loadConfigFromDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*.{php,json,ini,yaml,yml}', GLOB_BRACE) ?: [];

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $format = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if ($filename === 'helpers') {
                continue;
            }

            try {
                $data = $this->parseConfigFile($file, $format);
            } catch (\Throwable) {
                $data = [];
            }

            $this->config[$filename] = array_replace_recursive(
                $this->config[$filename] ?? [],
                $data
            );
        }
    }

    public function load(string $file): void
    {
        if (!file_exists($file)) return;
        $filename = pathinfo($file, PATHINFO_FILENAME);
        $format = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        try {
            $data = $this->parseConfigFile($file, $format);
        } catch (\Throwable) {
            $data = [];
        }

        $this->config[$filename] = array_replace_recursive(
            $this->config[$filename] ?? [],
            $data
        );
        $this->saveToCache();
    }

    public function loadEnvironmentOverrides(): void
    {
        $envConfigDir = $this->configDir . '/' . $this->env;

        if (is_dir($envConfigDir)) {
            $files = glob($envConfigDir . '/*.{php,json,ini,yaml,yml}', GLOB_BRACE) ?: [];

            foreach ($files as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                $format = strtolower(pathinfo($file, PATHINFO_EXTENSION));

                try {
                    $data = $this->parseConfigFile($file, $format);
                } catch (\Throwable) {
                    $data = [];
                }

                $this->config[$filename] = array_replace_recursive(
                    $this->config[$filename] ?? [],
                    $data
                );
            }
        }
    }

    // -------------------------------------------------------------------------
    // Parsers
    // -------------------------------------------------------------------------

    private function parseConfigFile(string $file, string $format): array
    {
        return match ($format) {
            'php' => $this->parsePhpConfig($file),
            'json' => $this->parseJsonConfig((string) file_get_contents($file)),
            'ini' => $this->parseIniConfig((string) file_get_contents($file)),
            'yaml', 'yml' => $this->parseYamlConfig((string) file_get_contents($file)),
            default => [],
        };
    }

    private function parsePhpConfig(string $file): array
    {
        $result = include $file;
        return is_array($result) ? $result : [];
    }

    private function parseJsonConfig(string $content): array
    {
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON config: ' . json_last_error_msg());
        }
        return is_array($decoded) ? $decoded : [];
    }

    private function parseIniConfig(string $content): array
    {
        $result = parse_ini_string($content, true, INI_SCANNER_TYPED);
        return is_array($result) ? $result : [];
    }

    private function parseYamlConfig(string $content): array
    {
        $data = Yaml::parse($content);
        return is_array($data) ? $data : [];
    }

    // -------------------------------------------------------------------------
    // Public API
    // -------------------------------------------------------------------------

    public function get(string $key, mixed $default = null): mixed
    {
        // Level-1 key cache: avoid repeated dot-split on hot paths
        if (array_key_exists($key, $this->keyCache)) {
            $value = $this->keyCache[$key];
            return $value ?? $default;
        }

        $keys = explode('.', $key);
        $current = $this->config;

        foreach ($keys as $k) {
            if (is_array($current) && array_key_exists($k, $current)) {
                $current = $current[$k];
            } else {
                $this->keyCache[$key] = null;
                return $default;
            }
        }

        $this->keyCache[$key] = $current;
        return $current;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function all(): array
    {
        return $this->config;
    }

    public function getEnvironment(): string
    {
        return $this->env;
    }

    public function set(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $current[$k] = $value;
            } else {
                if (!isset($current[$k]) || !is_array($current[$k])) {
                    $current[$k] = [];
                }
                $current = &$current[$k];
            }
        }

        // Invalidate key cache on write
        $this->keyCache = [];
    }
}
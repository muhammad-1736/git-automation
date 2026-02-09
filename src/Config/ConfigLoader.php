<?php

namespace Mu\CherryPicker\Config;

class ConfigLoader
{
    private static ?ConfigLoader $instance = null;
    private array $config = [];
    private bool $initialized = false;

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Initialize the configuration loader
     *
     * @param string $basePath Base path of the application (where .env file is located)
     * @param string $configPath Path to the config directory
     */
    public function initialize(string $basePath, string $configPath): void
    {
        if ($this->initialized) {
            return;
        }

        if (file_exists($basePath . '/.env')) {
            $dotenv = \Dotenv\Dotenv::createImmutable($basePath);
            $dotenv->load();
        }

        $this->loadConfigFiles($configPath);

        $this->initialized = true;
    }

    /**
     * Load configuration files from the config directory
     */
    private function loadConfigFiles(string $configPath): void
    {
        $configFiles = glob($configPath . '/*.php');

        foreach ($configFiles as $file) {
            // Skip this class file to avoid redeclaration
            if (str_contains($file, 'ConfigLoader.php')) continue;

            $configName = basename($file, '.php');
            $this->config[$configName] = require $file;
        }
    }

    /**
     * Get a configuration value using dot notation
     *
     * @param string $key Configuration key in dot notation (e.g., 'cherry-picker.git.username')
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Set a configuration value
     *
     * @param string $key Configuration key in dot notation
     * @param mixed $value Value to set
     */
    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        while (count($keys) > 1) {
            $key = array_shift($keys);

            if (!isset($config[$key]) || !is_array($config[$key])) {
                $config[$key] = [];
            }

            $config = &$config[$key];
        }

        $config[array_shift($keys)] = $value;
    }

    /**
     * Get all configuration
     */
    public function all(): array
    {
        return $this->config;
    }
}

<?php

/**
 * Cherry Picker Helper Functions
 * 
 * Provides global helper functions similar to Laravel's helpers
 * but using the standalone ConfigLoader
 */

use Mu\CherryPicker\Config\ConfigLoader;
use Psr\Container\ContainerInterface;

if (!function_exists('config')) {
    /**
     * Get a configuration value using dot notation
     *
     * @param string|null $key Configuration key in dot notation (e.g., 'cherry-picker.git.username')
     *                          If null, returns all configuration
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    function config(?string $key = null, $default = null): mixed
    {
        global $container;
        
        if (!isset($container) || !($container instanceof ContainerInterface)) {
            throw new \RuntimeException('Container is not initialized. Call config() only after the application container is set up.');
        }
        
        $configLoader = $container->get(ConfigLoader::class);

        if ($key === null) {
            return $configLoader->all();
        }

        return $configLoader->get($key, $default);
    }
}

if (!function_exists('env')) {
    /**
     * Get an environment variable
     *
     * @param string $key Environment variable name
     * @param mixed $default Default value if not found
     * @return mixed
     */
    function env(string $key, $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

if (!function_exists('data_get')) {
    /**
     * Get an item from an array using "dot" notation
     *
     * @param array $array The array to search in
     * @param string $key The key to search for
     * @param mixed $default The default value if the key is not found
     * @return mixed
     */
    function data_get(array $array, string $key, $default = null): mixed
    {
        if (isset($array[$key])) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (is_array($array) || array_key_exists($segment, $array)) {
                $array = $array[$segment];
            } else {
                return $default;
            }
        }

        return $array;
    }
}

if (!function_exists('dd')) {
    /**
     * Dump the given variables and end the script (Laravel-style)
     *
     * @param mixed ...$vars Variables to dump
     * @return never
     */
    function dd(mixed ...$vars): never
    {
        foreach ($vars as $var) {
            var_dump($var);
        }
        exit(1);
    }
}

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

if (!function_exists('get_release_or_hotfix_branch')) {
    /**
     * Resolve the reference branch name (release or hotfix) based on the fix version.
     *
     * @param string $fixVersion The fix version (e.g. 13.2.0, 13.2.5)
     * @return string
     */
    function get_release_or_hotfix_branch(string $fixVersion): string
    {
        $parts = explode('.', $fixVersion);

        if (isset($parts[2]) && $parts[2] === '0' && !isset($parts[3])) {
            return "release/{$fixVersion}";
        }

        return "hotfix/{$fixVersion}";
    }
}

if (!function_exists('select_platform')) {
    /**
     * Prompt user to select platform and return it.
     *
     * @return string
     */
    function select_platform(): string
    {
        return \Laravel\Prompts\search(
            label: 'Select platform',
            options: function (string $value) {
                $options = [
                    'cbc' => 'cbc',
                    'drl' => 'drl',
                    'dwj' => 'dwj',
                    'for' => 'for',
                    'gmt' => 'gmt',
                    'lprs' => 'lprs',
                    'mjtv' => 'mjtv',
                    'mms' => 'mms',
                    'nzme' => 'nzme',
                    'outsd' => 'outsd',
                    'pris' => 'pris',
                    'pstm' => 'pstm',
                    'score' => 'score',
                    'univision' => 'univision',
                    'wimt' => 'wimt',
                    'wlh' => 'wlh',
                    'wpc' => 'wpc',
                    'zlw' => 'zlw',
                ];

                if ($value === '') {
                    return $options;
                }

                return array_filter(
                    $options,
                    fn ($option) => str_contains(strtolower($option), strtolower($value))
                );
            }
        );
    }
}

if (!function_exists('resolve_gitlab_group')) {
    /**
     * Resolve the GitLab group name from a Jira ticket number.
     *
     * @param string $ticketNumber
     * @return string
     */
    function resolve_gitlab_group(string $ticketNumber): string
    {
        $ticketParts = explode('-', $ticketNumber);
        $prefix = strtoupper($ticketParts[0] ?? '');

        return match (true) {
            str_starts_with($prefix, 'SCOR')   => 'scr',
            str_starts_with($prefix, 'ZILLOW') => 'zlw',
            str_starts_with($prefix, 'UN')     => 'uni',
            str_starts_with($prefix, 'LP')     => 'lprs',
            str_starts_with($prefix, 'PM0')    => 'pstm',
            str_starts_with($prefix, 'OM')     => 'outsd',
            default                            => strtolower($prefix),
        };
    }
}

if (!function_exists('resolve_core_version')) {
    /**
     * Resolve the core version from composer.json for a given ticket and fix version.
     *
     * @param \Mu\CherryPicker\Contracts\VCSProviderContract $vcsProvider
     * @param string|null $ticketNumber
     * @param string $fixVersion
     * @param string|null $groupName
     * @return string
     */
    function resolve_core_version(\Mu\CherryPicker\Contracts\VCSProviderContract $vcsProvider, ?string $ticketNumber, string $fixVersion, ?string $groupName = null): string
    {
        $refBranchName = get_release_or_hotfix_branch($fixVersion);

        if ($groupName === null) {
            $groupName = !empty($ticketNumber) ? resolve_gitlab_group($ticketNumber) : '';
        }

        $composerJsonContent = $vcsProvider->getFileContent($groupName, 'platform', 'composer.json', $refBranchName);

        $composerData = json_decode($composerJsonContent, true);

        if (!is_array($composerData)) {
            throw new \RuntimeException("Failed to parse composer.json as JSON.");
        }

        $sigmaCoreVersion = data_get($composerData, 'require.sigma/core');
        if (!$sigmaCoreVersion) {
            throw new \RuntimeException("Could not find require.sigma/core inside composer.json.");
        }

        // Strip 'dev-' prefix if present
        $cloneFixVersionBranchName = $sigmaCoreVersion;
        if (str_starts_with($sigmaCoreVersion, 'dev-')) {
            $cloneFixVersionBranchName = substr($sigmaCoreVersion, 4);
        }

        return $cloneFixVersionBranchName;
    }
}


<?php

namespace Mu\CherryPicker\Workflows;

use Mu\CherryPicker\Config\ConfigLoader;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class UpdateCoreDependencyWorkflow
{
    public function __construct(
        private ?ConfigLoader $configLoader = null,
        private ?string $basePath = null,
    ) {}

    public function execute(): void
    {
        // 1. Select platform
        $platform = select_platform();

        // 2. Ask for branch
        $branch = text(
            label: 'Enter branch for sigma/core',
            placeholder: 'platform/7.4.3/drl',
            required: true,
        );

        // 3. Ask for commit hash
        $commitHash = text(
            label: 'Enter commit hash',
            placeholder: '3bafb744b83a9144cab895ec8fc4d80bb4a1aa56',
            required: true,
        );

        $this->updateDependency($platform, $branch, $commitHash);
    }

    public function updateDependency(string $platform, string $branch, string $commitHash): bool
    {
        $platform = trim($platform);
        $branch = trim($branch);
        $commitHash = trim($commitHash);

        if (str_starts_with($branch, 'dev-')) {
            $branch = substr($branch, 4);
        }
        $version = "dev-{$branch}";

        $baseDir = $this->basePath;
        if ($baseDir === null) {
            try {
                $baseDir = config('cherry-picker.git.base_document_path', '/home/xmsi/documents');
            } catch (\Throwable) {
                $baseDir = '/home/xmsi/documents';
            }
        }

        $path = "{$baseDir}/{$platform}";

        if (!is_dir($path)) {
            error("Platform directory not found at: {$path}");
            return false;
        }

        // 1. Update composer.json
        $composerJsonPath = "{$path}/composer.json";
        if (!file_exists($composerJsonPath)) {
            error("composer.json not found at: {$composerJsonPath}");
            return false;
        }

        $composerJsonContent = file_get_contents($composerJsonPath);
        $composerData = json_decode($composerJsonContent, true);
        if (!is_array($composerData)) {
            error("Failed to parse composer.json as valid JSON: {$composerJsonPath}");
            return false;
        }

        if (!isset($composerData['require']) || !is_array($composerData['require'])) {
            $composerData['require'] = [];
        }
        $composerData['require']['sigma/core'] = $version;

        $encodedJson = json_encode($composerData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($composerJsonPath, $encodedJson);
        info("Updated composer.json require.sigma/core to: {$version}");

        // 2. Update composer.lock
        $composerLockPath = "{$path}/composer.lock";
        if (!file_exists($composerLockPath)) {
            error("composer.lock not found at: {$composerLockPath}");
            return false;
        }

        $composerLockContent = file_get_contents($composerLockPath);
        $lockData = json_decode($composerLockContent, true);
        if (!is_array($lockData)) {
            error("Failed to parse composer.lock as valid JSON: {$composerLockPath}");
            return false;
        }

        $currentTime = gmdate('Y-m-d\TH:i:s+00:00');
        $found = false;

        foreach (['packages', 'packages-dev'] as $section) {
            if (isset($lockData[$section]) && is_array($lockData[$section])) {
                foreach ($lockData[$section] as &$package) {
                    if (isset($package['name']) && $package['name'] === 'sigma/core') {
                        $package['version'] = $version;
                        $package['source']['reference'] = $commitHash;
                        // if (isset($package['dist']) && is_array($package['dist'])) {
                        //     $package['dist']['reference'] = $commitHash;
                        // }
                        $package['time'] = $currentTime;
                        $found = true;
                        break 2;
                    }
                }
                unset($package);
            }
        }

        if (!$found) {
            warning("Package sigma/core was not found in {$composerLockPath} packages.");
        } else {
            $encodedLock = json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
            file_put_contents($composerLockPath, $encodedLock);
            info("Updated composer.lock sigma/core: version to {$version}, reference to {$commitHash}, time to {$currentTime}");
        }

        info("✅ Core dependency update completed successfully!");
        return true;
    }
}

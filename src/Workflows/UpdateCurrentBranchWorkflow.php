<?php

namespace Mu\CherryPicker\Workflows;

use Mu\CherryPicker\Config\ConfigLoader;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class UpdateCurrentBranchWorkflow
{
    public function __construct(
        private ?ConfigLoader $configLoader = null,
        private ?string $basePath = null,
    ) {}

    public function execute(): void
    {
        // 1. Select platform
        $platform = select_platform();

        // 2. Ask for commit hash
        $commitHash = text(
            label: 'Enter commit hash',
            placeholder: '3bafb744b83a9144cab895ec8fc4d80bb4a1aa56',
            required: true,
        );

        $this->updateCommitHash($platform, $commitHash);
    }

    public function updateCommitHash(string $platform, string $commitHash): bool
    {
        $platform = trim($platform);
        $commitHash = trim($commitHash);

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

        // Update composer.lock only
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
                        if (!isset($package['source']) || !is_array($package['source'])) {
                            $package['source'] = [];
                        }
                        $package['source']['reference'] = $commitHash;
                        if (isset($package['dist']) && is_array($package['dist'])) {
                            $package['dist']['reference'] = $commitHash;
                        }
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
            return false;
        }

        $encodedLock = json_encode($lockData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        file_put_contents($composerLockPath, $encodedLock);
        info("Updated composer.lock sigma/core: reference to {$commitHash}, time to {$currentTime}");
        info("✅ Current branch commit hash updated successfully!");

        return true;
    }
}

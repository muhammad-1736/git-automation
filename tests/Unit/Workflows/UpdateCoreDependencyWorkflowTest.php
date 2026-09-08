<?php

use Mu\CherryPicker\Workflows\UpdateCoreDependencyWorkflow;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Key;

describe('UpdateCoreDependencyWorkflow', function () {
    $tempDir = '';

    beforeEach(function () use (&$tempDir) {
        $tempDir = sys_get_temp_dir() . '/cherry_picker_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . '/drl', 0777, true);
    });

    afterEach(function () use (&$tempDir) {
        if (!empty($tempDir) && is_dir($tempDir)) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($files as $fileinfo) {
                $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
                $todo($fileinfo->getRealPath());
            }
            rmdir($tempDir);
        }
    });

    it('successfully updates composer.json and composer.lock with user inputs', function () use (&$tempDir) {
        $platformPath = $tempDir . '/drl';

        $initialComposerJson = [
            'name' => 'platform/drl',
            'require' => [
                'php' => '^8.3',
                'sigma/core' => 'dev-old/branch',
            ],
        ];
        file_put_contents(
            $platformPath . '/composer.json',
            json_encode($initialComposerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $initialComposerLock = [
            '_readme' => ['test'],
            'packages' => [
                [
                    'name' => 'sigma/core',
                    'version' => 'dev-old/branch',
                    'source' => [
                        'type' => 'git',
                        'url' => 'https://gitlab.da.local/px/platform.git',
                        'reference' => 'oldcommithash1234567890',
                    ],
                    'time' => '2025-01-01T00:00:00+00:00',
                ],
                [
                    'name' => 'other/package',
                    'version' => '1.0.0',
                ],
            ],
            'packages-dev' => [],
        ];
        file_put_contents(
            $platformPath . '/composer.lock',
            json_encode($initialComposerLock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        Prompt::fake([
            // 1. select_platform (search prompt: type 'drl', down, enter)
            'drl',
            Key::DOWN,
            Key::ENTER,
            // 2. branch
            'platform/7.4.3/drl',
            Key::ENTER,
            // 3. commit hash
            '3bafb744b83a9144cab895ec8fc4d80bb4a1aa56',
            Key::ENTER,
        ]);

        $workflow = new UpdateCoreDependencyWorkflow(basePath: $tempDir);
        $workflow->execute();

        // Check composer.json
        $updatedJson = json_decode(file_get_contents($platformPath . '/composer.json'), true);
        expect($updatedJson['require']['sigma/core'])->toBe('dev-platform/7.4.3/drl');

        // Check composer.lock
        $updatedLock = json_decode(file_get_contents($platformPath . '/composer.lock'), true);
        $sigmaPackage = collect($updatedLock['packages'])->firstWhere('name', 'sigma/core');
        expect($sigmaPackage)->not->toBeNull();
        expect($sigmaPackage['version'])->toBe('dev-platform/7.4.3/drl');
        expect($sigmaPackage['source']['reference'])->toBe('3bafb744b83a9144cab895ec8fc4d80bb4a1aa56');
        expect($sigmaPackage['time'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/');
    });

    it('strips dev- prefix if user enters branch already starting with dev-', function () use (&$tempDir) {
        $platformPath = $tempDir . '/drl';

        $initialComposerJson = [
            'require' => [
                'sigma/core' => 'dev-initial',
            ],
        ];
        file_put_contents(
            $platformPath . '/composer.json',
            json_encode($initialComposerJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $initialComposerLock = [
            'packages' => [
                [
                    'name' => 'sigma/core',
                    'version' => 'dev-initial',
                    'source' => [
                        'reference' => 'initialhash',
                    ],
                ],
            ],
        ];
        file_put_contents(
            $platformPath . '/composer.lock',
            json_encode($initialComposerLock, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        Prompt::fake([
            'drl',
            Key::DOWN,
            Key::ENTER,
            'dev-hotfix/1.2.3',
            Key::ENTER,
            'abcdef1234567890',
            Key::ENTER,
        ]);

        $workflow = new UpdateCoreDependencyWorkflow(basePath: $tempDir);
        $workflow->execute();

        $updatedJson = json_decode(file_get_contents($platformPath . '/composer.json'), true);
        expect($updatedJson['require']['sigma/core'])->toBe('dev-hotfix/1.2.3');

        $updatedLock = json_decode(file_get_contents($platformPath . '/composer.lock'), true);
        $sigmaPackage = collect($updatedLock['packages'])->firstWhere('name', 'sigma/core');
        expect($sigmaPackage['version'])->toBe('dev-hotfix/1.2.3');
        expect($sigmaPackage['source']['reference'])->toBe('abcdef1234567890');
    });

    it('updates package when located in packages-dev in composer.lock', function () use (&$tempDir) {
        $platformPath = $tempDir . '/drl';

        file_put_contents($platformPath . '/composer.json', json_encode(['require' => []]));
        file_put_contents($platformPath . '/composer.lock', json_encode([
            'packages' => [],
            'packages-dev' => [
                [
                    'name' => 'sigma/core',
                    'version' => 'dev-initial',
                    'source' => ['reference' => 'init_source'],
                ],
            ],
        ]));

        $workflow = new UpdateCoreDependencyWorkflow(basePath: $tempDir);
        $result = $workflow->updateDependency('drl', 'feature/dev-test', '999888777');

        expect($result)->toBeTrue();

        $updatedLock = json_decode(file_get_contents($platformPath . '/composer.lock'), true);
        $sigmaPackage = collect($updatedLock['packages-dev'])->firstWhere('name', 'sigma/core');
        expect($sigmaPackage['version'])->toBe('dev-feature/dev-test');
        expect($sigmaPackage['source']['reference'])->toBe('999888777');
    });

    it('returns false when platform directory does not exist', function () use (&$tempDir) {
        $workflow = new UpdateCoreDependencyWorkflow(basePath: $tempDir);
        $result = $workflow->updateDependency('nonexistent_platform', 'main', 'hash123');

        expect($result)->toBeFalse();
    });

    it('returns false when composer.json is missing', function () use (&$tempDir) {
        $workflow = new UpdateCoreDependencyWorkflow(basePath: $tempDir);
        $result = $workflow->updateDependency('drl', 'main', 'hash123');

        expect($result)->toBeFalse();
    });

    it('returns false when composer.lock is missing', function () use (&$tempDir) {
        $platformPath = $tempDir . '/drl';
        file_put_contents($platformPath . '/composer.json', json_encode(['require' => []]));

        $workflow = new UpdateCoreDependencyWorkflow(basePath: $tempDir);
        $result = $workflow->updateDependency('drl', 'main', 'hash123');

        expect($result)->toBeFalse();
    });

    it('warns when sigma/core is not present in composer.lock', function () use (&$tempDir) {
        $platformPath = $tempDir . '/drl';
        file_put_contents($platformPath . '/composer.json', json_encode(['require' => []]));
        file_put_contents($platformPath . '/composer.lock', json_encode([
            'packages' => [
                ['name' => 'other/package', 'version' => '1.0.0'],
            ],
        ]));

        $workflow = new UpdateCoreDependencyWorkflow(basePath: $tempDir);
        $result = $workflow->updateDependency('drl', 'main', 'hash123');

        expect($result)->toBeTrue();

        $updatedJson = json_decode(file_get_contents($platformPath . '/composer.json'), true);
        expect($updatedJson['require']['sigma/core'])->toBe('dev-main');
    });
});

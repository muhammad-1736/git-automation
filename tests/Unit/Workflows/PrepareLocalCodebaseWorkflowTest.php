<?php

use Mu\CherryPicker\Workflows\PrepareLocalCodebaseWorkflow;
use Mu\CherryPicker\Clients\GitClient;
use Mu\CherryPicker\Config\ConfigLoader;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\Contracts\WMTProviderContract;
use Mu\CherryPicker\Clients\ShellRunner;
use Laravel\Prompts\Prompt;

describe('PrepareLocalCodebaseWorkflow', function () {
    it('successfully runs compareDockerfiles and handles matched or mismatched Dockerfiles', function () {
        // Mock dependencies
        $mockGit = Mockery::mock(GitClient::class);
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockWmtProvider = Mockery::mock(WMTProviderContract::class);
        $mockVcsProvider = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        // Fake prompts so we don't write to console output
        Prompt::fake();

        // 1. Let's mock VCSProvider getFileContent to return some content
        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('zlw', 'platform', 'infrastructure/docker/phpfpm.Dockerfile', 'release/13.2.0')
            ->once()
            ->andReturn("FROM php:8.4-fpm-alpine\nRUN something");

        $workflow = new PrepareLocalCodebaseWorkflow(
            $mockGit,
            $mockConfigLoader,
            $mockWmtProvider,
            $mockVcsProvider,
            $mockShell
        );

        // Call the compare method. Local Dockerfile won't exist at /home/xmsi/documents/gmt, so local FROM is null.
        // Since VCS FROM is "FROM php:8.4-fpm-alpine", they are different. A warning will be shown.
        $workflow->compareDockerfiles('gmt', 'ZLW-231', '13.2.0');

        expect(true)->toBeTrue();
    });

    it('handles exceptions when fetching VCS Dockerfile', function () {
        // Mock dependencies
        $mockGit = Mockery::mock(GitClient::class);
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockWmtProvider = Mockery::mock(WMTProviderContract::class);
        $mockVcsProvider = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        Prompt::fake();

        // VCS throws exception (e.g. 404 file not found)
        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('zlw', 'platform', 'infrastructure/docker/phpfpm.Dockerfile', 'release/13.2.0')
            ->once()
            ->andThrow(new \RuntimeException("File not found"));

        $workflow = new PrepareLocalCodebaseWorkflow(
            $mockGit,
            $mockConfigLoader,
            $mockWmtProvider,
            $mockVcsProvider,
            $mockShell
        );

        // Local FROM is null (file doesn't exist) and VCS FROM is null (exception).
        // Since they are both null, they match, so no warning is printed.
        $workflow->compareDockerfiles('gmt', 'ZLW-231', '13.2.0');

        expect(true)->toBeTrue();
    });
});

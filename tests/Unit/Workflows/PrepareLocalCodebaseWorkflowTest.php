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

    it('handles compareDockerfiles with null ticketNumber using platform', function () {
        $mockGit = Mockery::mock(GitClient::class);
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockWmtProvider = Mockery::mock(WMTProviderContract::class);
        $mockVcsProvider = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        Prompt::fake();

        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('gmt', 'platform', 'infrastructure/docker/phpfpm.Dockerfile', 'release/13.2.0')
            ->once()
            ->andReturn("FROM php:8.4-fpm-alpine");

        $workflow = new PrepareLocalCodebaseWorkflow(
            $mockGit,
            $mockConfigLoader,
            $mockWmtProvider,
            $mockVcsProvider,
            $mockShell
        );

        $workflow->compareDockerfiles('gmt', null, '13.2.0');

        expect(true)->toBeTrue();
    });

    it('handles compareDockerfiles with univision platform mapping to uni when ticketNumber is null', function () {
        $mockGit = Mockery::mock(GitClient::class);
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockWmtProvider = Mockery::mock(WMTProviderContract::class);
        $mockVcsProvider = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        Prompt::fake();

        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('uni', 'platform', 'infrastructure/docker/phpfpm.Dockerfile', 'release/13.2.0')
            ->once()
            ->andReturn("FROM php:8.4-fpm-alpine");

        $workflow = new PrepareLocalCodebaseWorkflow(
            $mockGit,
            $mockConfigLoader,
            $mockWmtProvider,
            $mockVcsProvider,
            $mockShell
        );

        $workflow->compareDockerfiles('univision', null, '13.2.0');

        expect(true)->toBeTrue();
    });

    it('executes workflow when option 1 (input fix version) is selected', function () {
        $mockGit = Mockery::mock(GitClient::class);
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockWmtProvider = Mockery::mock(WMTProviderContract::class);
        $mockVcsProvider = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        Prompt::fake([
            // 1. select_platform (search prompt: type 'zlw', move down to select, enter)
            'zlw',
            \Laravel\Prompts\Key::DOWN,
            \Laravel\Prompts\Key::ENTER,
            // 2. Select option (default is fix_version, 1st option)
            \Laravel\Prompts\Key::ENTER,
            // 3. Please provide the fixVersion
            '13.2.0',
            \Laravel\Prompts\Key::ENTER,
        ]);

        $composerJsonContent = json_encode([
            'require' => [
                'sigma/core' => 'dev-platform/13.2.0/scr'
            ]
        ]);

        // VCS composer.json fetch uses 'zlw' (the platform from select_platform, NOT resolve_gitlab_group)
        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('zlw', 'platform', 'composer.json', 'release/13.2.0')
            ->once()
            ->andReturn($composerJsonContent);

        // VCS Dockerfile fetch uses 'zlw'
        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('zlw', 'platform', 'infrastructure/docker/phpfpm.Dockerfile', 'release/13.2.0')
            ->once()
            ->andReturn("FROM php:8.4-fpm-alpine");

        // Platform repo setup
        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/zlw')
            ->once();
        $mockGit->shouldReceive('prepare')->with('zlw')->once();
        $mockShell->shouldReceive('run')->with("git fetch origin 'release/13.2.0'")->once();
        $mockShell->shouldReceive('runShellExec')->with("git status --porcelain")->once()->andReturn("");
        $mockShell->shouldReceive('run')->with("git checkout 'release/13.2.0'")->once();
        $mockShell->shouldReceive('run')->with("git pull origin 'release/13.2.0'")->once();

        // Core repo setup
        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/zlw/vendor/sigma/core')
            ->once();
        $mockGit->shouldReceive('prepare')->with('px')->once();
        $mockShell->shouldReceive('run')->with("git fetch origin 'platform/13.2.0/scr'")->once();
        $mockShell->shouldReceive('runShellExec')->with("git status --porcelain")->once()->andReturn("");
        $mockShell->shouldReceive('run')->with("git checkout 'platform/13.2.0/scr'")->once();
        $mockShell->shouldReceive('run')->with("git pull origin 'platform/13.2.0/scr'")->once();

        $workflow = new PrepareLocalCodebaseWorkflow(
            $mockGit,
            $mockConfigLoader,
            $mockWmtProvider,
            $mockVcsProvider,
            $mockShell
        );

        $workflow->execute();

        expect(true)->toBeTrue();
    });

    it('executes workflow when option 2 (input jira ticket) is selected', function () {
        $mockGit = Mockery::mock(GitClient::class);
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockWmtProvider = Mockery::mock(WMTProviderContract::class);
        $mockVcsProvider = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        Prompt::fake([
            // 1. select_platform (search prompt: type 'gmt', move down to select, enter)
            'gmt',
            \Laravel\Prompts\Key::DOWN,
            \Laravel\Prompts\Key::ENTER,
            // 2. Select option -> down arrow to select 'jira_ticket' (2nd option)
            \Laravel\Prompts\Key::DOWN,
            \Laravel\Prompts\Key::ENTER,
            // 3. TicketResolver prompt: ticket number
            'ZLW-231',
            \Laravel\Prompts\Key::ENTER,
        ]);

        $mockWmtProvider->shouldReceive('getFeatureOrBug')->with('ZLW-231')->once()->andReturn('feature');
        $mockWmtProvider->shouldReceive('getFixVersion')->with('ZLW-231')->once()->andReturn('13.2.0');
        $mockWmtProvider->shouldReceive('getSummary')->with('ZLW-231')->once()->andReturn('Some Summary');

        $composerJsonContent = json_encode([
            'require' => [
                'sigma/core' => 'dev-platform/13.2.0/scr'
            ]
        ]);

        // VCS composer.json fetch uses 'zlw' from resolve_gitlab_group('ZLW-231')
        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('zlw', 'platform', 'composer.json', 'release/13.2.0')
            ->once()
            ->andReturn($composerJsonContent);

        // VCS Dockerfile fetch uses 'zlw' from resolve_gitlab_group('ZLW-231')
        $mockVcsProvider->shouldReceive('getFileContent')
            ->with('zlw', 'platform', 'infrastructure/docker/phpfpm.Dockerfile', 'release/13.2.0')
            ->once()
            ->andReturn("FROM php:8.4-fpm-alpine");

        // Platform repo setup
        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/gmt')
            ->once();
        $mockGit->shouldReceive('prepare')->with('gmt')->once();
        $mockShell->shouldReceive('run')->with("git fetch origin 'release/13.2.0'")->once();
        $mockShell->shouldReceive('runShellExec')->with("git status --porcelain")->once()->andReturn("");
        $mockShell->shouldReceive('run')->with("git checkout 'release/13.2.0'")->once();
        $mockShell->shouldReceive('run')->with("git pull origin 'release/13.2.0'")->once();

        // Core repo setup
        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/gmt/vendor/sigma/core')
            ->once();
        $mockGit->shouldReceive('prepare')->with('px')->once();
        $mockShell->shouldReceive('run')->with("git fetch origin 'platform/13.2.0/scr'")->once();
        $mockShell->shouldReceive('runShellExec')->with("git status --porcelain")->once()->andReturn("");
        $mockShell->shouldReceive('run')->with("git checkout 'platform/13.2.0/scr'")->once();
        $mockShell->shouldReceive('run')->with("git pull origin 'platform/13.2.0/scr'")->once();

        $workflow = new PrepareLocalCodebaseWorkflow(
            $mockGit,
            $mockConfigLoader,
            $mockWmtProvider,
            $mockVcsProvider,
            $mockShell
        );

        $workflow->execute();

        expect(true)->toBeTrue();
    });
});

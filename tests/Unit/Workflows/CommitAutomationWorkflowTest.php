<?php

use Mu\CherryPicker\Workflows\CommitAutomationWorkflow;
use Mu\CherryPicker\Config\ConfigLoader;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\Contracts\WMTProviderContract;
use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\Clients\GitClient;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Key;

describe('CommitAutomationWorkflow', function () {
    it('successfully executes commit automation workflow', function () {
        $configLoader = $GLOBALS['container']->get(ConfigLoader::class);
        $mockGit = Mockery::mock(GitClient::class);
        $mockWmt = Mockery::mock(WMTProviderContract::class);
        $mockVcs = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        // Mock GitClient
        $mockGit->shouldReceive('prepare')->once();

        // Mock WMT / Jira
        $mockWmt->shouldReceive('getFeatureOrBug')->with('SCOR-123')->andReturn('bugfix');
        $mockWmt->shouldReceive('getFixVersion')->with('SCOR-123')->andReturn('12.0.0');
        $mockWmt->shouldReceive('getSummary')->with('SCOR-123')->andReturn('Fix something');

        // Mock Shell interactions
        $mockShell->shouldReceive('runShellExec')
            ->with('git branch --show-current')
            ->once()
            ->andReturn('master');

        $mockShell->shouldReceive('run')
            ->with('git stash')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git pull origin master')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git stash pop')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git checkout -b bugfix/SCOR-123')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git add .')
            ->once();

        $mockShell->shouldReceive('run')
            ->with("git commit -m 'SCOR-123 Fix something'")
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git push origin bugfix/SCOR-123')
            ->once();

        // Mock VCS / GitLab Merge Request creation
        $mockVcs->shouldReceive('createMergeRequest')
            ->with(
                'px',
                'bugfix/SCOR-123',
                'master',
                'SCOR-123 Fix something',
                '', // empty description
                Mockery::any(),
                Mockery::any()
            )
            ->once();

        // Simulate prompts
        Prompt::fake([
            // 1. Platform select ('score')
            's', 'c', 'o', 'r', 'e',
            Key::DOWN,
            Key::ENTER,
            // 2. Repository type ('core')
            Key::DOWN,
            Key::ENTER,
            // 3. Ticket Number ('SCOR-123')
            'SCOR-123',
            Key::ENTER,
            // 4. Extra commit message (empty)
            Key::ENTER,
        ]);

        $workflow = new CommitAutomationWorkflow($mockGit, $configLoader, $mockWmt, $mockVcs, $mockShell);
        $workflow->execute();

        $path = $configLoader->get('cherry-picker.git.path_to_root_folder');
        expect($path)->toBe('/home/xmsi/documents/score/vendor/sigma/core');
    });

    it('does not checkout if already on the target branch', function () {
        $configLoader = $GLOBALS['container']->get(ConfigLoader::class);
        $mockGit = Mockery::mock(GitClient::class);
        $mockWmt = Mockery::mock(WMTProviderContract::class);
        $mockVcs = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        // Mock GitClient
        $mockGit->shouldReceive('prepare')->once();

        // Mock WMT / Jira
        $mockWmt->shouldReceive('getFeatureOrBug')->with('SCOR-123')->andReturn('bugfix');
        $mockWmt->shouldReceive('getFixVersion')->with('SCOR-123')->andReturn('12.0.0');
        $mockWmt->shouldReceive('getSummary')->with('SCOR-123')->andReturn('Fix something');

        // Mock Shell interactions
        $mockShell->shouldReceive('runShellExec')
            ->with('git branch --show-current')
            ->once()
            ->andReturn('bugfix/SCOR-123');

        $mockShell->shouldReceive('run')
            ->with('git stash')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git pull origin bugfix/SCOR-123')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git stash pop')
            ->once();

        // Ensure checkout is NOT called
        $mockShell->shouldNotReceive('run')
            ->with('git checkout -b bugfix/SCOR-123');

        $mockShell->shouldReceive('run')
            ->with('git add .')
            ->once();

        $mockShell->shouldReceive('run')
            ->with("git commit -m 'SCOR-123 Fix something'")
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git push origin bugfix/SCOR-123')
            ->once();

        // Mock VCS / GitLab Merge Request creation - should not be called since source and target branch are the same
        $mockVcs->shouldNotReceive('createMergeRequest');

        // Simulate prompts
        Prompt::fake([
            // 1. Platform select ('score')
            's', 'c', 'o', 'r', 'e',
            Key::DOWN,
            Key::ENTER,
            // 2. Repository type ('core')
            Key::DOWN,
            Key::ENTER,
            // 3. Ticket Number ('SCOR-123')
            'SCOR-123',
            Key::ENTER,
            // 4. Extra commit message (empty)
            Key::ENTER,
        ]);

        $workflow = new CommitAutomationWorkflow($mockGit, $configLoader, $mockWmt, $mockVcs, $mockShell);
        $workflow->execute();

        $path = $configLoader->get('cherry-picker.git.path_to_root_folder');
        expect($path)->toBe('/home/xmsi/documents/score/vendor/sigma/core');
    });

    it('successfully executes commit automation workflow with extra commit message', function () {
        $configLoader = $GLOBALS['container']->get(ConfigLoader::class);
        $mockGit = Mockery::mock(GitClient::class);
        $mockWmt = Mockery::mock(WMTProviderContract::class);
        $mockVcs = Mockery::mock(VCSProviderContract::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        // Mock GitClient
        $mockGit->shouldReceive('prepare')->once();

        // Mock WMT / Jira
        $mockWmt->shouldReceive('getFeatureOrBug')->with('SCOR-123')->andReturn('bugfix');
        $mockWmt->shouldReceive('getFixVersion')->with('SCOR-123')->andReturn('12.0.0');
        $mockWmt->shouldReceive('getSummary')->with('SCOR-123')->andReturn('Fix something');

        // Mock Shell interactions
        $mockShell->shouldReceive('runShellExec')
            ->with('git branch --show-current')
            ->once()
            ->andReturn('master');

        $mockShell->shouldReceive('run')
            ->with('git stash')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git pull origin master')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git stash pop')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git checkout -b bugfix/SCOR-123')
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git add .')
            ->once();

        $mockShell->shouldReceive('run')
            ->with("git commit -m 'SCOR-123 Fix something > Refactored logic'")
            ->once();

        $mockShell->shouldReceive('run')
            ->with('git push origin bugfix/SCOR-123')
            ->once();

        // Mock VCS / GitLab Merge Request creation
        $mockVcs->shouldReceive('createMergeRequest')
            ->with(
                'px',
                'bugfix/SCOR-123',
                'master',
                'SCOR-123 Fix something',
                '', // empty description
                Mockery::any(),
                Mockery::any()
            )
            ->once();

        // Simulate prompts
        Prompt::fake([
            // 1. Platform select ('score')
            's', 'c', 'o', 'r', 'e',
            Key::DOWN,
            Key::ENTER,
            // 2. Repository type ('core')
            Key::DOWN,
            Key::ENTER,
            // 3. Ticket Number ('SCOR-123')
            'SCOR-123',
            Key::ENTER,
            // 4. Extra commit message
            'Refactored logic',
            Key::ENTER,
        ]);

        $workflow = new CommitAutomationWorkflow($mockGit, $configLoader, $mockWmt, $mockVcs, $mockShell);
        $workflow->execute();

        $path = $configLoader->get('cherry-picker.git.path_to_root_folder');
        expect($path)->toBe('/home/xmsi/documents/score/vendor/sigma/core');
    });
});

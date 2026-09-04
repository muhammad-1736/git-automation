<?php

use Mu\CherryPicker\Config\ConfigLoader;
use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\Workflows\CleanOldBranchesWorkflow;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Key;

describe('CleanOldBranchesWorkflow', function () {
    it('successfully cleans existing feature and bugfix branches in both platform and core repositories', function () {
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        Prompt::fake([
            // 1. select_platform (search prompt: type 'zlw', move down to select, enter)
            'zlw',
            Key::DOWN,
            Key::ENTER,
            // 2. text prompt: ticket number
            'ZLW-231',
            Key::ENTER,
        ]);

        // Platform repo config
        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/zlw')
            ->once();

        // Platform repo branch checks and deletions
        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'feature/ZLW-231'")
            ->once()
            ->andReturn("feature/ZLW-231");
        $mockShell->shouldReceive('run')
            ->with("git branch -D 'feature/ZLW-231'")
            ->once();

        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'bugfix/ZLW-231'")
            ->once()
            ->andReturn("bugfix/ZLW-231");
        $mockShell->shouldReceive('run')
            ->with("git branch -D 'bugfix/ZLW-231'")
            ->once();

        // Core repo config
        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/zlw/vendor/sigma/core')
            ->once();

        // Core repo branch checks and deletions
        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'feature/ZLW-231'")
            ->once()
            ->andReturn("feature/ZLW-231");
        $mockShell->shouldReceive('run')
            ->with("git branch -D 'feature/ZLW-231'")
            ->once();

        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'bugfix/ZLW-231'")
            ->once()
            ->andReturn("");
        // bugfix branch didn't exist in core, so run('git branch -D ...') should NOT be called

        $workflow = new CleanOldBranchesWorkflow($mockConfigLoader, $mockShell);
        $workflow->execute();

        expect(true)->toBeTrue();
    });

    it('strips feature/ prefix from ticket input and cleans branches', function () {
        $mockConfigLoader = Mockery::mock(ConfigLoader::class);
        $mockShell = Mockery::mock(ShellRunner::class);

        Prompt::fake([
            // 1. select_platform
            'score',
            Key::DOWN,
            Key::ENTER,
            // 2. ticket input with prefix
            'feature/SCOR-100',
            Key::ENTER,
        ]);

        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/score')
            ->once();

        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'feature/SCOR-100'")
            ->once()
            ->andReturn("");

        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'bugfix/SCOR-100'")
            ->once()
            ->andReturn("");

        $mockConfigLoader->shouldReceive('set')
            ->with('cherry-picker.git.path_to_root_folder', '/home/xmsi/documents/score/vendor/sigma/core')
            ->once();

        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'feature/SCOR-100'")
            ->once()
            ->andReturn("");

        $mockShell->shouldReceive('runShellExec')
            ->with("git branch --list 'bugfix/SCOR-100'")
            ->once()
            ->andReturn("");

        $workflow = new CleanOldBranchesWorkflow($mockConfigLoader, $mockShell);
        $workflow->execute();

        expect(true)->toBeTrue();
    });
});

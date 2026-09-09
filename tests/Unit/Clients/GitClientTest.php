<?php

use Mu\CherryPicker\Clients\GitClient;
use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\Contracts\VCSProviderContract;

describe('GitClient', function () {
    it('returns initial branch name', function () {
        $mockShell = Mockery::mock(ShellRunner::class);
        $mockVcs = Mockery::mock(VCSProviderContract::class);

        $mockShell->shouldReceive('runShellExec')
            ->with('git rev-parse --abbrev-ref HEAD')
            ->once()
            ->andReturn('master');

        $gitClient = new GitClient($mockShell, $mockVcs);

        expect($gitClient->getInitialBranchName())->toBe('master');
    });

    it('prepares git repository with default remote origin and configs', function () {
        $mockShell = Mockery::mock(ShellRunner::class);
        $mockVcs = Mockery::mock(VCSProviderContract::class);

        $mockVcs->shouldReceive('getPAT')
            ->once()
            ->andReturn('sample-pat-token');

        $username = config('cherry-picker.git.username');
        $email = config('cherry-picker.git.email');

        $mockShell->shouldReceive('run')->with('git config core.fileMode false')->once();
        $mockShell->shouldReceive('run')->with('git config pull.rebase true')->once();
        $mockShell->shouldReceive('run')->with('git config checkout.defaultRemote origin')->once();
        $mockShell->shouldReceive('run')->with('git config user.name "' . $username . '"')->once();
        $mockShell->shouldReceive('run')->with('git config user.email "' . $email . '"')->once();
        $mockShell->shouldReceive('run')->with('git remote set-url origin https://oauth2:sample-pat-token@gitlab.da.local/px/platform.git')->once();

        $gitClient = new GitClient($mockShell, $mockVcs);
        $gitClient->prepare('px');
    });
});

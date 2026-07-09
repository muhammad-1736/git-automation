<?php

namespace Mu\CherryPicker;

// Define a namespace-specific sleep function to bypass 3-second sleep during testing.
if (!function_exists('Mu\CherryPicker\sleep')) {
    function sleep($seconds) {
        // No-op for fast tests!
    }
}

use Mu\CherryPicker\CherryPicker;
use Mu\CherryPicker\Clients\GitClient;
use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\DataTransferObjects\CherryPickDTO;
use Mu\CherryPicker\Config\ConfigLoader;
use Mockery;

describe('CherryPicker', function () {
    beforeEach(function () {
        $this->vcs = Mockery::mock(VCSProviderContract::class);
        $this->git = Mockery::mock(GitClient::class);
        $this->shell = Mockery::mock(ShellRunner::class);
        $this->cherryPicker = new CherryPicker($this->vcs, $this->git, $this->shell);

        $this->dto = new CherryPickDTO(
            ticketNumber: 'SCOR-123',
            fixVersion: '12.0.0',
            commitHashes: 'hash123',
            ticketBranchName: 'bugfix/SCOR-123',
            titleMR: 'SCOR-123 Fix something',
            cloneFixVersionBranchName: null
        );

        $configLoader = $GLOBALS['container']->get(ConfigLoader::class);
        $configLoader->set('cherry-picker.git.username', 'test-user');
        $configLoader->set('cherry-picker.git.mr_reviewer_name', 'reviewer-user');
    });

    afterEach(function () {
        Mockery::close();
    });

    it('creates a new branch when it does not exist locally or on remote', function () {
        $this->git->shouldReceive('getInitialBranchName')->once()->andReturn('master');
        $this->git->shouldReceive('prepare')->once();

        // No uncommitted changes
        $this->shell->shouldReceive('runShellExec')
            ->with('git status --porcelain -uno')
            ->once()
            ->andReturn('');

        $this->shell->shouldReceive('run')->with('git fetch origin')->once();
        $this->shell->shouldReceive('run')->with('git checkout -B release/12.0.0 origin/release/12.0.0')->once();
        $this->shell->shouldReceive('run')->with('git pull origin release/12.0.0')->once();

        // Check local branch existence (returns non-zero / does not exist)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/heads/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 1, 'error_output' => '']);

        // Check remote branch existence (returns non-zero / does not exist)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/remotes/origin/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 1, 'error_output' => '']);

        // Should checkout a new branch
        $this->shell->shouldReceive('run')->with('git checkout -b bugfix/SCOR-123')->once();

        // Cherry pick success
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git cherry-pick -x hash123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        $this->shell->shouldReceive('run')->with('git push origin bugfix/SCOR-123')->once();
        $this->shell->shouldReceive('run')->with('git checkout master')->once();

        $this->vcs->shouldReceive('createMergeRequest')->once();

        $this->cherryPicker->execute($this->dto);
    });

    it('checks out local branch when it exists locally and remote does not exist', function () {
        $this->git->shouldReceive('getInitialBranchName')->once()->andReturn('master');
        $this->git->shouldReceive('prepare')->once();

        // No uncommitted changes
        $this->shell->shouldReceive('runShellExec')
            ->with('git status --porcelain -uno')
            ->once()
            ->andReturn('');

        $this->shell->shouldReceive('run')->with('git fetch origin')->once();
        $this->shell->shouldReceive('run')->with('git checkout -B release/12.0.0 origin/release/12.0.0')->once();
        $this->shell->shouldReceive('run')->with('git pull origin release/12.0.0')->once();

        // Check local branch existence (returns zero / exists)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/heads/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        // Check remote branch existence (returns non-zero / does not exist)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/remotes/origin/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 1, 'error_output' => '']);

        // Should checkout the local branch and not pull
        $this->shell->shouldReceive('run')->with('git checkout bugfix/SCOR-123')->once();

        // Cherry pick success
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git cherry-pick -x hash123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        $this->shell->shouldReceive('run')->with('git push origin bugfix/SCOR-123')->once();
        $this->shell->shouldReceive('run')->with('git checkout master')->once();

        $this->vcs->shouldReceive('createMergeRequest')->once();

        $this->cherryPicker->execute($this->dto);
    });

    it('checks out local branch and pulls when it exists locally and also on remote', function () {
        $this->git->shouldReceive('getInitialBranchName')->once()->andReturn('master');
        $this->git->shouldReceive('prepare')->once();

        // No uncommitted changes
        $this->shell->shouldReceive('runShellExec')
            ->with('git status --porcelain -uno')
            ->once()
            ->andReturn('');

        $this->shell->shouldReceive('run')->with('git fetch origin')->once();
        $this->shell->shouldReceive('run')->with('git checkout -B release/12.0.0 origin/release/12.0.0')->once();
        $this->shell->shouldReceive('run')->with('git pull origin release/12.0.0')->once();

        // Check local branch existence (returns zero / exists)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/heads/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        // Check remote branch existence (returns zero / exists)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/remotes/origin/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        // Should checkout and pull
        $this->shell->shouldReceive('run')->with('git checkout bugfix/SCOR-123')->once();
        $this->shell->shouldReceive('run')->with('git pull origin bugfix/SCOR-123')->once();

        // Cherry pick success
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git cherry-pick -x hash123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        $this->shell->shouldReceive('run')->with('git push origin bugfix/SCOR-123')->once();
        $this->shell->shouldReceive('run')->with('git checkout master')->once();

        $this->vcs->shouldReceive('createMergeRequest')->once();

        $this->cherryPicker->execute($this->dto);
    });

    it('checks out remote branch with --track and pulls when it only exists on remote', function () {
        $this->git->shouldReceive('getInitialBranchName')->once()->andReturn('master');
        $this->git->shouldReceive('prepare')->once();

        // No uncommitted changes
        $this->shell->shouldReceive('runShellExec')
            ->with('git status --porcelain -uno')
            ->once()
            ->andReturn('');

        $this->shell->shouldReceive('run')->with('git fetch origin')->once();
        $this->shell->shouldReceive('run')->with('git checkout -B release/12.0.0 origin/release/12.0.0')->once();
        $this->shell->shouldReceive('run')->with('git pull origin release/12.0.0')->once();

        // Check local branch existence (returns non-zero / does not exist)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/heads/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 1, 'error_output' => '']);

        // Check remote branch existence (returns zero / exists)
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git show-ref --verify refs/remotes/origin/bugfix/SCOR-123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        // Should checkout tracking and pull
        $this->shell->shouldReceive('run')->with('git checkout --track origin/bugfix/SCOR-123')->once();
        $this->shell->shouldReceive('run')->with('git pull origin bugfix/SCOR-123')->once();

        // Cherry pick success
        $this->shell->shouldReceive('runWithoutOutputAndError')
            ->with('git cherry-pick -x hash123')
            ->once()
            ->andReturn(['exit_code' => 0, 'error_output' => '']);

        $this->shell->shouldReceive('run')->with('git push origin bugfix/SCOR-123')->once();
        $this->shell->shouldReceive('run')->with('git checkout master')->once();

        $this->vcs->shouldReceive('createMergeRequest')->once();

        $this->cherryPicker->execute($this->dto);
    });
});

<?php

namespace Mu\CherryPicker;

use Mu\CherryPicker\Clients\GitClient;
use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\DataTransferObjects\CherryPickDTO;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\pause;
use function Laravel\Prompts\error;

class CherryPicker
{
    public function __construct(
        protected VCSProviderContract $vcs,
        protected GitClient $git,
        protected ShellRunner $shell,
    ) {
    }

    /**
     * @throws \RuntimeException If conflict occurs (caught by Command)
     */
    public function execute(CherryPickDTO $dto): void
    {
        try {
            $initialBranch = $this->git->getInitialBranchName();

            // Prepare git global username email, rebase on pull
            $this->git->prepare();

            $this->shell->run("git stash push");
            $this->shell->run("git pull origin {$dto->fixVersionBranchName()}");
            $this->shell->run("git checkout {$dto->fixVersionBranchName()}");
            $this->shell->run("git checkout -b $dto->ticketBranchName");
            $this->shell->run("git fetch origin");
            $result = $this->shell->runWithoutOutputAndError("git cherry-pick -x $dto->commitHashes");  // cherry-picking

            if ($result['exit_code'] !== 0) {
                warning("A merge conflict occurred! \n\n EXIT CODE: {$result['exit_code']}");

                $this->conflictResolution();
            }

            $this->shell->run("git push origin $dto->ticketBranchName"); // pushing
            $this->shell->run("git checkout $initialBranch");
            $this->shell->run("git branch -D $dto->ticketBranchName"); // deleting
            $this->shell->run("git stash pop");

            // Create an MR
            $this->vcs->createMergeRequest(
                'px',
                $dto->ticketBranchName,
                $dto->fixVersionBranchName(),
                $dto->titleMR,
                null,
                config('cherry-picker.git.username'),
                config('cherry-picker.git.mr_reviewer_name'),
            );
        } catch (\Exception $e) {
            warning($e->getMessage());die;
        }
    }

    public function conflictResolution(): void
    {
        pause('Please resolve the conflicts manually in your editor, save the files, and then press Enter to continue. (We will add them to stage, commit and push)');

        \Laravel\Prompts\info('Resuming... Stage the resolved files and continuing cherry-pick.');

        $this->shell->runWithoutOutputAndError('git add .');
        $resume = $this->shell->runWithoutOutputAndError('git cherry-pick --continue');

        if ($resume['exit_code'] === 0) {
            \Laravel\Prompts\info('Cherry-pick completed successfully! 😎');
        } else {
            error('Still having trouble 🥲: ' . $resume['error_output']);
            $this->conflictResolution();
        }
    }
}

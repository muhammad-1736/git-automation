<?php

namespace Mu\CherryPicker\Clients;

use Mu\CherryPicker\Contracts\VCSProviderContract;

class GitClient
{
    public function __construct(
        private readonly ShellRunner $shell,
        private readonly VCSProviderContract $vcs,
    ) {
    }

    public function getInitialBranchName(): string
    {
        return $this->shell->runShellExec("git rev-parse --abbrev-ref HEAD");
    }

    public function prepare(): void
    {
        $vcs_pat = $this->vcs->getPAT();
        
        $this->shell->run("git config core.fileMode false");
        $this->shell->run("git config pull.rebase true");
        $this->shell->run('git config --global user.name "' . config('cherry-picker.git.username') . '"');
        $this->shell->run('git config --global user.email "' . config('cherry-picker.git.email') . '"');
        $this->shell->run("git remote set-url origin https://oauth2:$vcs_pat@gitlab.da.local/px/platform.git");
    }
}

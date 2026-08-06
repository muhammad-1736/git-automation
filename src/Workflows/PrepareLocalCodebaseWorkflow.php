<?php

namespace Mu\CherryPicker\Workflows;

use Mu\CherryPicker\Clients\GitClient;
use Mu\CherryPicker\Config\ConfigLoader;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\Contracts\WMTProviderContract;
use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\TicketResolver;
use function Laravel\Prompts\info;
use function Laravel\Prompts\error;

class PrepareLocalCodebaseWorkflow
{
    public function __construct(
        private GitClient $git,
        private ConfigLoader $configLoader,
        private WMTProviderContract $wmtProvider,
        private VCSProviderContract $vcsProvider,
        private ShellRunner $shell,
    ) {}

    public function execute(): void
    {
        // 1. Select platform
        $platform = select_platform();
        $path = "/home/xmsi/documents/{$platform}";

        // 2. Map platform for gitlab px/uni mapping after setting path
        $platformMapped = ($platform === 'univision' ? 'uni' : $platform);

        // 3. Ask for Jira ticket details and resolve core version
        $ticketDetails = TicketResolver::resolve($this->wmtProvider);
        $ticketNumber = $ticketDetails['ticketNumber'];
        $fixVersion   = $ticketDetails['fixVersion'];

        $refBranchName = get_release_or_hotfix_branch($fixVersion);

        try {
            $cloneFixVersionBranchName = resolve_core_version($this->vcsProvider, $ticketNumber, $fixVersion);
            info("Successfully resolved core side branch from composer.json: {$cloneFixVersionBranchName}");
        } catch (\Exception $e) {
            error('Failed to retrieve or parse composer.json: ' . $e->getMessage());
            exit(1);
        }

        // 4. Onboarding platform repo work
        info("Preparing platform codebase at: {$path}");
        $this->configLoader->set('cherry-picker.git.path_to_root_folder', $path);
        
        try {
            $this->git->prepare($platformMapped);
            
            info("Fetching origin...");
            $this->shell->run("git fetch origin");

            $statusOutput = $this->shell->runShellExec("git status --porcelain");
            $hasLocalChanges = !empty($statusOutput);
            if ($hasLocalChanges) {
                info("Stashing local changes...");
                $this->shell->run("git stash");
            }

            info("Checking out branch {$refBranchName}...");
            $this->shell->run("git checkout " . escapeshellarg($refBranchName));
            
            info("Pulling latest changes...");
            $this->shell->run("git pull origin " . escapeshellarg($refBranchName));

            if ($hasLocalChanges) {
                info("Popping stashed changes...");
                $this->shell->run("git stash pop");
            }

            // Delete existing (feature|bugfix)/ticketNumber local branch
            $branchesToDelete = [
                "feature/{$ticketNumber}",
                "bugfix/{$ticketNumber}"
            ];
            foreach ($branchesToDelete as $branchToDelete) {
                $branchExists = $this->shell->runShellExec("git branch --list " . escapeshellarg($branchToDelete));
                if (!empty($branchExists)) {
                    info("Deleting local branch {$branchToDelete}...");
                    $this->shell->run("git branch -D " . escapeshellarg($branchToDelete));
                }
            }
        } catch (\Exception $e) {
            error("Error during platform repository preparation: " . $e->getMessage());
            exit(1);
        }

        // 5. Sigma core repo work
        $corePath = "{$path}/vendor/sigma/core";
        info("Preparing core codebase at: {$corePath}");
        $this->configLoader->set('cherry-picker.git.path_to_root_folder', $corePath);

        try {
            $this->git->prepare('px');

            info("Fetching origin...");
            $this->shell->run("git fetch origin");

            $statusOutput = $this->shell->runShellExec("git status --porcelain");
            $hasLocalChanges = !empty($statusOutput);
            if ($hasLocalChanges) {
                info("Stashing local changes...");
                $this->shell->run("git stash");
            }

            info("Checking out branch {$cloneFixVersionBranchName}...");
            $this->shell->run("git checkout " . escapeshellarg($cloneFixVersionBranchName));

            info("Pulling latest changes...");
            $this->shell->run("git pull origin " . escapeshellarg($cloneFixVersionBranchName));

            if ($hasLocalChanges) {
                info("Popping stashed changes...");
                $this->shell->run("git stash pop");
            }

            // Delete existing (feature|bugfix)/ticketNumber local branch
            foreach ($branchesToDelete as $branchToDelete) {
                $branchExists = $this->shell->runShellExec("git branch --list " . escapeshellarg($branchToDelete));
                if (!empty($branchExists)) {
                    info("Deleting local branch {$branchToDelete}...");
                    $this->shell->run("git branch -D " . escapeshellarg($branchToDelete));
                }
            }
        } catch (\Exception $e) {
            error("Error during core repository preparation: " . $e->getMessage());
            exit(1);
        }

        info("✅ Local codebase preparation completed successfully!");
    }
}

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
use function Laravel\Prompts\warning;

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

        $onboardingBranchName = get_release_or_hotfix_branch($fixVersion);

        try {
            $coreBranchName = resolve_core_version($this->vcsProvider, $ticketNumber, $fixVersion);
            info("Successfully resolved core side branch from composer.json: {$coreBranchName}");
        } catch (\Exception $e) {
            error('Failed to retrieve or parse composer.json: ' . $e->getMessage());
            exit(1);
        }

        $this->compareDockerfiles($platform, $ticketNumber, $fixVersion);

        // 4. Onboarding platform repo work
        info("Preparing platform codebase at: {$path}");
        $this->configLoader->set('cherry-picker.git.path_to_root_folder', $path);
        
        try {
            $this->git->prepare($platformMapped);
            
            info("Fetching origin...");
            $this->shell->run("git fetch origin " . escapeshellarg($onboardingBranchName));

            $statusOutput = $this->shell->runShellExec("git status --porcelain");
            $hasLocalChanges = !empty($statusOutput);
            $stashed = false;
            if ($hasLocalChanges) {
                info("Stashing local changes...");
                $stashOutput = $this->shell->runShellExec("git stash");
                if (stripos($stashOutput, 'Saved working directory') !== false) {
                    $stashed = true;
                }
            }

            info("Checking out branch {$onboardingBranchName}...");
            $this->shell->run("git checkout " . escapeshellarg($onboardingBranchName));
            
            info("Pulling latest changes...");
            $this->shell->run("git pull origin " . escapeshellarg($onboardingBranchName));

            if ($stashed) {
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
            $this->shell->run("git fetch origin " . escapeshellarg($coreBranchName));

            $statusOutput = $this->shell->runShellExec("git status --porcelain");
            $hasLocalChanges = !empty($statusOutput);
            $stashed = false;
            if ($hasLocalChanges) {
                info("Stashing local changes...");
                $stashOutput = $this->shell->runShellExec("git stash");
                if (stripos($stashOutput, 'Saved working directory') !== false) {
                    $stashed = true;
                }
            }

            info("Checking out branch {$coreBranchName}...");
            $this->shell->run("git checkout " . escapeshellarg($coreBranchName));

            info("Pulling latest changes...");
            $this->shell->run("git pull origin " . escapeshellarg($coreBranchName));

            if ($stashed) {
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

    /**
     * Compare local phpfpm.Dockerfile FROM line with VCS GitLab version
     *
     * @param string $platform
     * @param string $ticketNumber
     * @param string $fixVersion
     * @return void
     */
    public function compareDockerfiles(string $platform, string $ticketNumber, string $fixVersion): void
    {
        $path = "/home/xmsi/documents/{$platform}";
        $onboardingBranchName = get_release_or_hotfix_branch($fixVersion);

        $localDockerfilePath = "{$path}/infrastructure/docker/phpfpm.Dockerfile";
        $localContent = file_exists($localDockerfilePath) ? file_get_contents($localDockerfilePath) : null;

        $groupName = resolve_gitlab_group($ticketNumber);

        try {
            $vcsContent = $this->vcsProvider->getFileContent(
                $groupName,
                'platform',
                'infrastructure/docker/phpfpm.Dockerfile',
                $onboardingBranchName
            );
        } catch (\Exception $e) {
            $vcsContent = null;
        }

        $extractFromLine = function (?string $content): ?string {
            if (empty($content)) {
                return null;
            }
            $lines = explode("\n", $content);
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (preg_match('/^FROM\s+/i', $trimmed)) {
                    return $trimmed;
                }
            }
            return null;
        };

        $localFromLine = $extractFromLine($localContent);
        $vcsFromLine = $extractFromLine($vcsContent);

        if ($localFromLine !== $vcsFromLine) {
            $localLineDisplay = $localFromLine ?? '(not found or has no FROM line)';
            $vcsLineDisplay = $vcsFromLine ?? '(not found or has no FROM line)';

            $warningMsg = "Dockerfile base images (FROM lines) are different!\n\n"
                . "  📍 Local file:\n"
                . "     \e[31m{$localLineDisplay}\e[0m\n\n"
                . "  🌐 GitLab VCS ({$onboardingBranchName}):\n"
                . "     \e[32m{$vcsLineDisplay}\e[0m\n\n"
                . "Maybe you will need to re-dockerize your space (delete old containers:)? ";
            warning($warningMsg);
        }
    }
}

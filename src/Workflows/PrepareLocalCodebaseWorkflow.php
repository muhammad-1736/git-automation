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
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

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

        $option = select(
            label: 'Select option',
            options: [
                'fix_version' => '1. Input fix version',
                'jira_ticket' => '2. Input jira ticket',
            ],
            default: 'fix_version',
        );

        $isJiraTicket = in_array($option, ['jira_ticket', '2', 2, '2. Input jira ticket', '2. input jira ticket', 'Input jira ticket', 'input jira ticket'], true);

        if ($isJiraTicket) {
            // 3. Ask for Jira ticket details and resolve core version
            $ticketDetails = TicketResolver::resolve($this->wmtProvider);
            $ticketNumber = $ticketDetails['ticketNumber'];
            $fixVersion   = $ticketDetails['fixVersion'];
            $targetGroup  = null;
        } else {
            $fixVersion = text(
                label: 'Please provide the fixVersion (e.g. 12.0.1)',
                placeholder: '12.0.1',
                required: true
            );
            $ticketNumber = null;
            $targetGroup  = $platformMapped;
        }

        $onboardingBranchName = get_release_or_hotfix_branch($fixVersion);

        try {
            $coreBranchName = resolve_core_version($this->vcsProvider, $ticketNumber, $fixVersion, $targetGroup);
            info("Successfully resolved core side branch from composer.json: {$coreBranchName}");
        } catch (\Exception $e) {
            error('Failed to retrieve or parse composer.json: ' . $e->getMessage());
            exit(1);
        }

        $this->compareDockerfiles($platform, $ticketNumber, $fixVersion, $targetGroup);

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
     * @param string|null $ticketNumber
     * @param string $fixVersion
     * @param string|null $groupName
     * @return void
     */
    public function compareDockerfiles(string $platform, ?string $ticketNumber, string $fixVersion, ?string $groupName = null): void
    {
        $path = "/home/xmsi/documents/{$platform}";
        $onboardingBranchName = get_release_or_hotfix_branch($fixVersion);

        $localDockerfilePath = "{$path}/infrastructure/docker/phpfpm.Dockerfile";
        $localContent = file_exists($localDockerfilePath) ? file_get_contents($localDockerfilePath) : null;

        if ($groupName === null) {
            $groupName = !empty($ticketNumber)
                ? resolve_gitlab_group($ticketNumber)
                : ($platform === 'univision' ? 'uni' : $platform);
        }

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

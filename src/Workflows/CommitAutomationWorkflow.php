<?php

namespace Mu\CherryPicker\Workflows;

use Mu\CherryPicker\Clients\GitClient;
use Mu\CherryPicker\Config\ConfigLoader;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\Contracts\WMTProviderContract;
use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\TicketResolver;
use function Laravel\Prompts\select;
use function Laravel\Prompts\search;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;

class CommitAutomationWorkflow
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
        $platform = search(
            label: 'Select platform',
            options: function (string $value) {
                $options = [
                    'cbc' => 'cbc',
                    'drl' => 'drl',
                    'dwj' => 'dwj',
                    'for' => 'for',
                    'gmt' => 'gmt',
                    'lprs' => 'lprs',
                    'mjtv' => 'mjtv',
                    'mms' => 'mms',
                    'nzme' => 'nzme',
                    'outsd' => 'outsd',
                    'platform' => 'platform',
                    'pris' => 'pris',
                    'pstm' => 'pstm',
                    'score' => 'score',
                    'univision' => 'univision',
                    'wimt' => 'wimt',
                    'wlh' => 'wlh',
                    'wpc' => 'wpc',
                    'zlw' => 'zlw',
                ];

                if ($value === '') {
                    return $options;
                }

                return array_filter(
                    $options,
                    fn ($option) => str_contains(strtolower($option), strtolower($value))
                );
            }
        );

        $type = select(
            label: 'Select repository type',
            options: [
                'onboarding' => 'onboarding',
                'core' => 'core',
            ],
            default: 'onboarding'
        );

        $path = "/home/xmsi/documents/{$platform}";
        if ($type === 'core') {
            $path .= "/vendor/sigma/core";
        }

        $this->configLoader->set('cherry-picker.git.path_to_root_folder', $path);

        // Resolve JIRA details
        $ticketDetails = TicketResolver::resolve($this->wmtProvider);
        $ticketNumber = $ticketDetails['ticketNumber'];
        $featureOrBug = $ticketDetails['featureOrBug'];
        $titleMR      = $ticketDetails['titleMR'];

        $extraCommitMessage = text(
            label: 'Extra commit message',
            placeholder: 'Optional'
        );

        $commitDescription = textarea(
            label: 'Commit description',
            placeholder: 'Optional'
        );

        $commitMessage = $titleMR;
        if (!empty($extraCommitMessage)) {
            $commitMessage .= ' > ' . $extraCommitMessage;
        }

        $this->git->prepare();

        // Git checkout and branch flow
        $currentBranch = $this->shell->runShellExec("git branch --show-current");
        $this->shell->run("git stash");
        $this->shell->run("git pull origin $currentBranch");
        $this->shell->run("git stash pop");

        $ticketBranchName = "$featureOrBug/$ticketNumber";
        if ($currentBranch !== $ticketBranchName) {
            $this->shell->run("git checkout -b $ticketBranchName");
        }

        // Git add and commit with JIRA title
        $this->shell->run("git add .");
        
        if (!empty($commitDescription)) {
            $this->shell->run("git commit -m " . escapeshellarg($commitMessage) . " -m " . escapeshellarg($commitDescription));
        } else {
            $this->shell->run("git commit -m " . escapeshellarg($commitMessage));
        }

        // Git push to origin
        $this->shell->run("git push origin $ticketBranchName");

        // Create Merge Request
        if ($currentBranch !== $ticketBranchName) {
            $this->vcsProvider->createMergeRequest(
                'px',
                $ticketBranchName,
                $currentBranch,
                $titleMR,
                '', // without description
                config('cherry-picker.git.username'),
                config('cherry-picker.git.mr_reviewer_name'),
            );
        }

        info('✅ Commit Automation process completed successfully!');
    }
}

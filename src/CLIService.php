<?php

namespace Mu\CherryPicker;

use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\DataTransferObjects\CherryPickDTO;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use Mu\CherryPicker\CherryPicker;
use Mu\CherryPicker\Contracts\WMTProviderContract;
use Mu\CherryPicker\Workflows\CommitAutomationWorkflow;
use Mu\CherryPicker\Workflows\PrepareLocalCodebaseWorkflow;
use Mu\CherryPicker\Workflows\CleanOldBranchesWorkflow;
use Mu\CherryPicker\Workflows\UpdateCoreDependencyWorkflow;
use Mu\CherryPicker\Workflows\UpdateCurrentBranchWorkflow;
use Mu\CherryPicker\TicketResolver;

class CLIService
{
    private array $options = [];

    public function __construct(
        private WMTProviderContract $wmtProvider,
        private VCSProviderContract $vcsProvider,
        private CherryPicker $cherryPickService,
        private CommitAutomationWorkflow $commitAutomationWorkflow,
        private PrepareLocalCodebaseWorkflow $prepareLocalCodebaseWorkflow,
        private CleanOldBranchesWorkflow $cleanOldBranchesWorkflow,
        private UpdateCoreDependencyWorkflow $updateCoreDependencyWorkflow,
        private UpdateCurrentBranchWorkflow $updateCurrentBranchWorkflow,
    ) {}

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function execute(): void
    {
        note($this->getWelcomeAscii());

        $workflowType = select(
            label: 'What do you want to do?',
            options: [
                'prepare_local_codebase' => 'Prepare local codebase',
                'commit_automation'      => 'Commit automation',
                'clean_old_branches'     => 'Clean old branches',
                'update_core_dependency' => 'Update core dependency in composer',
                'update_current_branch'  => 'Update current core branch (composer.lock only)',
                'clone'                  => 'Clone',
                'merge_back_to_core'     => 'Merge back to core',
            ],
            default: 'prepare_local_codebase'
        );

        if ($workflowType === 'prepare_local_codebase') {
            $this->prepareLocalCodebaseWorkflow->execute();
            return;
        }

        if ($workflowType === 'commit_automation') {
            $this->commitAutomationWorkflow->execute();
            return;
        }

        if ($workflowType === 'clean_old_branches') {
            $this->cleanOldBranchesWorkflow->execute();
            return;
        }

        if ($workflowType === 'update_core_dependency') {
            $this->updateCoreDependencyWorkflow->execute();
            return;
        }

        if ($workflowType === 'update_current_branch') {
            $this->updateCurrentBranchWorkflow->execute();
            return;
        }

        $cloneFixVersionBranchName = null;

        $ticketDetails = TicketResolver::resolve($this->wmtProvider);
        $ticketNumber = $ticketDetails['ticketNumber'];
        $featureOrBug = $ticketDetails['featureOrBug'];
        $fixVersion   = $ticketDetails['fixVersion'];
        $titleMR      = $ticketDetails['titleMR'];

        // [START] Clone: get core fix version from composer.json
            if ($workflowType === 'clone') {
                try {
                    $cloneFixVersionBranchName = resolve_core_version($this->vcsProvider, $ticketNumber, $fixVersion);
                    \Laravel\Prompts\info("Successfully resolved core side branch from composer.json: {$cloneFixVersionBranchName}");
                } catch (\Exception $e) {
                    error('Failed to retrieve or parse composer.json: ' . $e->getMessage());
                    exit(1);
                }
            }
        // [END] Clone: get core fix version from composer.json

        $ticketBranchName = "$featureOrBug/$ticketNumber";

        // get commits from MR (default) or from user input based on --commits-strategy
        // [START] Get commits
            if ($this->option('commits-strategy')) {
                $commitsHashes = text(
                    label: 'What commits should I merge back? (Copy from Gitlab from MR Commits page) NO COMMA SEPARATION',
                    placeholder: 'bf222cf511d2581fbc65db304830c645ff25f18a d3a3e8e6db60e1fcb2ea2dfca18f078fa8388e8c 33cc28488ad3047a488d0c15896feeebbd607995',
                    required: true,
                );
            } else {
                $mergeRequests = text(
                    label: 'What Merge Requests should I merge back? Give me ids',
                    placeholder: '27469 27851',
                    required: true,
                );

                $commitsHashes = '';
                foreach (explode(' ', $mergeRequests) as $mergeRequest) {
                    try {
                        $commitsHashes .= ' ' . $this->vcsProvider->getMergeRequestCommits($mergeRequest);
                    } catch (\Exception $e) {
                        if ($e->getCode() == 404 && $e->getMessage() == 'No commits found') {
                            error($e->getMessage());
                            exit(1);
                        }

                        throw $e;
                    }
                }

                $commitsHashes = trim($commitsHashes);
            }
        // [END] Get commits

        info('Starting cherry-pick process...');
        $this->cherryPickService->execute(new CherryPickDTO(
            $ticketNumber,
            $fixVersion,
            $commitsHashes,
            $ticketBranchName,
            $titleMR,
            $cloneFixVersionBranchName
        ));
        info('✅ Cherry-pick completed successfully!');
    }

    private function option(string $key): mixed
    {
        return $this->options[$key] ?? null;
    }


    public function getWelcomeAscii(): string
    {
        return "
      ___           ___           ___         ___         ___           ___
     /  /\         /__/\         /  /\       /  /\       /  /\         /  /\          ___
    /  /:/_        \  \:\       /  /::\     /  /::\     /  /::\       /  /::\        /  /\
   /  /:/ /\        \  \:\     /  /:/\:\   /  /:/\:\   /  /:/\:\     /  /:/\:\      /  /:/
  /  /:/ /::\   ___  \  \:\   /  /:/~/:/  /  /:/~/:/  /  /:/  \:\   /  /:/~/:/     /  /:/
 /__/:/ /:/\:\ /__/\  \__\:\ /__/:/ /:/  /__/:/ /:/  /__/:/ \__\:\ /__/:/ /:/___  /  /::\
 \  \:\/:/~/:/ \  \:\ /  /:/ \  \:\/:/   \  \:\/:/   \  \:\ /  /:/ \  \:\/:::::/ /__/:/\:\
  \  \::/ /:/   \  \:\  /:/   \  \::/     \  \::/     \  \:\  /:/   \  \::/~~~~  \__\/  \:\
   \__\/ /:/     \  \:\/:/     \  \:\      \  \:\      \  \:\/:/     \  \:\           \  \:\
     /__/:/       \  \::/       \  \:\      \  \:\      \  \::/       \  \:\           \__\/
     \__\/         \__\/         \__\/       \__\/       \__\/         \__\/

                  ___           ___           ___
      ___        /  /\         /  /\         /__/\
     /  /\      /  /:/_       /  /::\       |  |::\
    /  /:/     /  /:/ /\     /  /:/\:\      |  |:|:\
   /  /:/     /  /:/ /:/_   /  /:/~/::\   __|__|:|\:\
  /  /::\    /__/:/ /:/ /\ /__/:/ /:/\:\ /__/::::| \:\
 /__/:/\:\   \  \:\/:/ /:/ \  \:\/:/__\/ \  \:\~~\__\/
 \__\/  \:\   \  \::/ /:/   \  \::/       \  \:\
      \  \:\   \  \:\/:/     \  \:\        \  \:\
       \__\/    \  \::/       \  \:\        \  \:\
                 \__\/         \__\/         \__\/

    ";
    }
}

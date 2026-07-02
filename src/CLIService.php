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

class CLIService
{
    private array $options = [];

    public function __construct(
        private WMTProviderContract $wmtProvider,
        private VCSProviderContract $vcsProvider,
        private CherryPicker $cherryPickService,
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
                'merge_back_to_core' => 'Merge back to core',
                'clone'              => 'Clone',
            ],
            default: 'merge_back_to_core'
        );

        $cloneFixVersionBranchName = null;

        $ticketNumber = text('Please provide me ticket', 'SOCS-test');

        // [START] WMT interaction
            try {
                $featureOrBug = $this->wmtProvider->getFeatureOrBug($ticketNumber);
                $fixVersion   = $this->wmtProvider->getFixVersion($ticketNumber);
                $titleMR      = $ticketNumber . ' ' . $this->wmtProvider->getSummary($ticketNumber);
            } catch (\Exception $e) {
                \Laravel\Prompts\warning('Jira access failed (' . $e->getMessage() . '). Falling back to manual input.');

                $issueType = select(
                    label: 'What is the issue type?',
                    options: [
                        'feature' => 'Feature',
                        'bug'     => 'Bug',
                    ],
                    default: 'bug'
                );
                $featureOrBug = $issueType;

                $fixVersion = text(
                    label: 'Please provide the fixVersion (e.g. 12.0.1)',
                    placeholder: '12.0.1',
                    required: true
                );

                $summary = text(
                    label: 'Please provide the ticket summary (for the Merge Request title)',
                    placeholder: 'Fix issue with database connection',
                    required: true
                );
                $titleMR = $ticketNumber . ' ' . $summary;
            }
        // [END] WMT interaction

        // [START] Clone: get core fix version from composer.json
            if ($workflowType === 'clone') {
                $refBranchName = get_release_or_hotfix_branch($fixVersion);

                try {
                    $ticketParts = explode('-', $ticketNumber);
                    $prefix = strtoupper($ticketParts[0] ?? '');

                    $groupName = match (true) {
                        str_starts_with($prefix, 'SCOR')   => 'scr',
                        str_starts_with($prefix, 'ZILLOW') => 'zlw',
                        str_starts_with($prefix, 'UN')     => 'uni',
                        str_starts_with($prefix, 'LP')     => 'lprs',
                        str_starts_with($prefix, 'OM')     => 'outsd',
                        default                            => strtolower($prefix),
                    };
            
                    $composerJsonContent = $this->vcsProvider->getFileContent($groupName, 'platform', 'composer.json', $refBranchName);

                    $composerData = json_decode($composerJsonContent, true);

                    if (!is_array($composerData)) {
                        throw new \RuntimeException("Failed to parse composer.json as JSON.");
                    }

                    $sigmaCoreVersion = data_get($composerData, 'require.sigma/core');
                    if (!$sigmaCoreVersion) {
                        throw new \RuntimeException("Could not find require.sigma/core inside composer.json.");
                    }

                    // Strip 'dev-' prefix if present
                    $cloneFixVersionBranchName = $sigmaCoreVersion;
                    if (str_starts_with($sigmaCoreVersion, 'dev-')) {
                        $cloneFixVersionBranchName = substr($sigmaCoreVersion, 4);
                    }

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

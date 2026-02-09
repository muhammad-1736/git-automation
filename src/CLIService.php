<?php

namespace Mu\CherryPicker;

use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\DataTransferObjects\CherryPickDTO;
use function Laravel\Prompts\text;
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

        $ticketNumber = text('Please provide me ticket', 'SOCS-test');

        // [START] WMT interaction
            try {
                $featureOrBug = $this->wmtProvider->getFeatureOrBug($ticketNumber);
                $fixVersion   = $this->wmtProvider->getFixVersion($ticketNumber);
                $titleMR      = $ticketNumber . ' ' . $this->wmtProvider->getSummary($ticketNumber);
            } catch (\Exception $e) {
                error($e->getMessage() . PHP_EOL . PHP_EOL . 'It is the trace where error happened:' . PHP_EOL . $e->getTraceAsString());
                exit(1);
            }
        // [END] WMT interaction

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
            $titleMR
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

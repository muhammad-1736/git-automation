<?php

namespace Mu\CherryPicker\Workflows;

use Mu\CherryPicker\Clients\ShellRunner;
use Mu\CherryPicker\Config\ConfigLoader;
use function Laravel\Prompts\info;
use function Laravel\Prompts\text;

class CleanOldBranchesWorkflow
{
    public function __construct(
        private ConfigLoader $configLoader,
        private ShellRunner $shell,
    ) {}

    public function execute(): void
    {
        // 1. Select platform
        $platform = select_platform();
        $path = "/home/xmsi/documents/{$platform}";

        // 2. Ask for ticket number
        $ticketNumber = text(
            label: 'Please provide me ticket',
            placeholder: 'SOCS-test',
            required: true
        );

        $ticketNumber = trim($ticketNumber);
        $ticketNumber = preg_replace('#^(feature|bugfix)/#', '', $ticketNumber);

        if (empty($ticketNumber)) {
            info('No ticket number provided.');
            return;
        }

        $branchesToDelete = [
            "feature/{$ticketNumber}",
            "bugfix/{$ticketNumber}",
        ];

        // 3. Platform repo branch cleanup
        info("Cleaning old branches in platform codebase at: {$path}");
        $this->configLoader->set('cherry-picker.git.path_to_root_folder', $path);

        foreach ($branchesToDelete as $branchToDelete) {
            $branchExists = $this->shell->runShellExec("git branch --list " . escapeshellarg($branchToDelete));
            if (!empty($branchExists)) {
                info("Deleting local branch {$branchToDelete}...");
                $this->shell->run("git branch -D " . escapeshellarg($branchToDelete));
            }
        }

        // 4. Core repo branch cleanup
        $corePath = "{$path}/vendor/sigma/core";
        info("Cleaning old branches in core codebase at: {$corePath}");
        $this->configLoader->set('cherry-picker.git.path_to_root_folder', $corePath);

        foreach ($branchesToDelete as $branchToDelete) {
            $branchExists = $this->shell->runShellExec("git branch --list " . escapeshellarg($branchToDelete));
            if (!empty($branchExists)) {
                info("Deleting local branch {$branchToDelete}...");
                $this->shell->run("git branch -D " . escapeshellarg($branchToDelete));
            }
        }

        info("✅ Old branches cleanup completed successfully!");
    }
}

<?php

namespace Mu\CherryPicker\DataTransferObjects;

readonly class CherryPickDTO
{
    public function __construct(
        public string $ticketNumber,
        public string $fixVersion, // e.g., 12.0.1
        public string $commitHashes,
        public string $ticketBranchName,
        public string $titleMR,
        public ?string $cloneFixVersionBranchName = null,
    ) {}

    public function fixVersionBranchName(): string
    {
        if ($this->cloneFixVersionBranchName) {
            return $this->cloneFixVersionBranchName;
        }

        return get_release_or_hotfix_branch($this->fixVersion);
    }
}
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
    ) {}

    public function fixVersionBranchName(): string
    {
        $parts = explode('.', $this->fixVersion);

        if (isset($parts[2]) && $parts[2] === '0' && !isset($parts[3])) {
            return "release/{$this->fixVersion}";
        }

        return "hotfix/{$this->fixVersion}";
    }
}
<?php

namespace Mu\CherryPicker\Contracts;

interface VCSProviderContract
{
    public function getMergeRequestCommits($mrIid): string;

    public function getPAT(): string;

    public function createMergeRequest(
        string $projectGroup,
        string $sourceBranch,
        string $targetBranch,
        string $title,
        ?string $description = null,
        ?string $assigneeName = null,
        ?string $reviewerName = null
    ): void;
}

<?php

use Mu\CherryPicker\DataTransferObjects\CherryPickDTO;

describe('CherryPickDTO', function () {
    it('returns the cloneFixVersionBranchName directly when it is provided', function () {
        $dto = new CherryPickDTO(
            ticketNumber: 'ZLW-231',
            fixVersion: '13.2.0',
            commitHashes: 'bf222cf511d2581fbc65db304830c645ff25f18a',
            ticketBranchName: 'feature/ZLW-231',
            titleMR: 'ZLW-231 Mocked Jira Summary',
            cloneFixVersionBranchName: 'platform/13.2.0/scr'
        );

        expect($dto->fixVersionBranchName())->toBe('platform/13.2.0/scr');
    });

    it('resolves the branch name from fixVersion when cloneFixVersionBranchName is null', function () {
        // Release branch version
        $dtoRelease = new CherryPickDTO(
            ticketNumber: 'ZLW-231',
            fixVersion: '13.2.0',
            commitHashes: 'bf222cf511d2581fbc65db304830c645ff25f18a',
            ticketBranchName: 'feature/ZLW-231',
            titleMR: 'ZLW-231 Mocked Jira Summary',
            cloneFixVersionBranchName: null
        );

        expect($dtoRelease->fixVersionBranchName())->toBe('release/13.2.0');

        // Hotfix branch version
        $dtoHotfix = new CherryPickDTO(
            ticketNumber: 'ZLW-231',
            fixVersion: '13.2.5',
            commitHashes: 'bf222cf511d2581fbc65db304830c645ff25f18a',
            ticketBranchName: 'feature/ZLW-231',
            titleMR: 'ZLW-231 Mocked Jira Summary',
            cloneFixVersionBranchName: null
        );

        expect($dtoHotfix->fixVersionBranchName())->toBe('hotfix/13.2.5');
    });
});

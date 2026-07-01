<?php

use Mu\CherryPicker\CLIService;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\Contracts\WMTProviderContract;
use Mu\CherryPicker\CherryPicker;
use Mu\CherryPicker\DataTransferObjects\CherryPickDTO;
use Laravel\Prompts\Prompt;
use Laravel\Prompts\Key;

describe('CLIService', function () {
    it('successfully executes clone workflow with dynamic branch resolution from composer.json', function () {
        $mockWmt = Mockery::mock(WMTProviderContract::class);
        $mockVcs = Mockery::mock(VCSProviderContract::class);
        $mockCherryPicker = Mockery::mock(CherryPicker::class);

        // Mock WMT (Jira)
        $mockWmt->shouldReceive('getFeatureOrBug')->with('ZLW-231')->andReturn('feature');
        $mockWmt->shouldReceive('getFixVersion')->with('ZLW-231')->andReturn('13.2.0');
        $mockWmt->shouldReceive('getSummary')->with('ZLW-231')->andReturn('Mocked Jira Summary');

        // Mock VCS (GitLab) for file fetch
        // Note: For ZLW-231, prefix is ZLW, groupName should be zlw.
        // For 13.2.0, third part is 0, so refBranchName should be release/13.2.0.
        $composerJsonContent = json_encode([
            'require' => [
                'sigma/core' => 'dev-platform/13.2.0/scr'
            ]
        ]);
        $mockVcs->shouldReceive('getFileContent')
            ->with('zlw', 'platform', 'composer.json', 'release/13.2.0')
            ->andReturn($composerJsonContent);

        // Mock VCS (GitLab) for merge request commits fetch
        $mockVcs->shouldReceive('getMergeRequestCommits')
            ->with('27469')
            ->andReturn('bf222cf511d2581fbc65db304830c645ff25f18a');

        // Mock CherryPicker service execution
        // We expect it to be called with a DTO where:
        // fixVersionBranchName() returns "platform/13.2.0/scr" because we stripped "dev-" from "dev-platform/13.2.0/scr"
        $mockCherryPicker->shouldReceive('execute')
            ->with(Mockery::on(function (CherryPickDTO $dto) {
                expect($dto->ticketNumber)->toBe('ZLW-231');
                expect($dto->fixVersion)->toBe('13.2.0');
                expect($dto->commitHashes)->toBe('bf222cf511d2581fbc65db304830c645ff25f18a');
                expect($dto->ticketBranchName)->toBe('feature/ZLW-231');
                expect($dto->titleMR)->toBe('ZLW-231 Mocked Jira Summary');
                expect($dto->fixVersionBranchName())->toBe('platform/13.2.0/scr');
                return true;
            }))
            ->once();

        // Fake CLI prompts answers:
        // 1. What do you want to do? -> select "clone" (we can simulate down arrow to select clone, or pass clone directly depending on Prompt::fake)
        // Let's use Prompt::fake with the exact text responses that Laravel Prompts expects
        Prompt::fake([
            // select prompt (clone is 2nd option, so we do Key::DOWN, then Key::ENTER)
            Key::DOWN,
            Key::ENTER,
            // text prompt for ticket: ZLW-231
            'ZLW-231',
            Key::ENTER,
            // text prompt for Merge Request ids: 27469
            '27469',
            Key::ENTER,
        ]);

        $cliService = new CLIService($mockWmt, $mockVcs, $mockCherryPicker);
        $cliService->execute();

        expect(true)->toBeTrue(); // If we get here without exception, mock was called successfully!
    });

    it('successfully handles SCOR-980 ticket mapping to scr group name', function () {
        $mockWmt = Mockery::mock(WMTProviderContract::class);
        $mockVcs = Mockery::mock(VCSProviderContract::class);
        $mockCherryPicker = Mockery::mock(CherryPicker::class);

        // Mock WMT (Jira)
        $mockWmt->shouldReceive('getFeatureOrBug')->with('SCOR-980')->andReturn('bugfix');
        $mockWmt->shouldReceive('getFixVersion')->with('SCOR-980')->andReturn('13.2.5');
        $mockWmt->shouldReceive('getSummary')->with('SCOR-980')->andReturn('Mocked Bug Fix Summary');

        // Mock VCS (GitLab) for file fetch
        // For SCOR-980, prefix is SCOR, groupName should be scr.
        // For 13.2.5, third part is 5, so refBranchName should be hotfix/13.2.5.
        $composerJsonContent = json_encode([
            'require' => [
                'sigma/core' => 'dev-platform/13.2.5/scr'
            ]
        ]);
        $mockVcs->shouldReceive('getFileContent')
            ->with('scr', 'platform', 'composer.json', 'hotfix/13.2.5')
            ->andReturn($composerJsonContent);

        // Mock VCS (GitLab) for merge request commits fetch
        $mockVcs->shouldReceive('getMergeRequestCommits')
            ->with('27469')
            ->andReturn('bf222cf511d2581fbc65db304830c645ff25f18a');

        $mockCherryPicker->shouldReceive('execute')
            ->with(Mockery::on(function (CherryPickDTO $dto) {
                expect($dto->ticketNumber)->toBe('SCOR-980');
                expect($dto->fixVersion)->toBe('13.2.5');
                expect($dto->commitHashes)->toBe('bf222cf511d2581fbc65db304830c645ff25f18a');
                expect($dto->ticketBranchName)->toBe('bugfix/SCOR-980');
                expect($dto->titleMR)->toBe('SCOR-980 Mocked Bug Fix Summary');
                expect($dto->fixVersionBranchName())->toBe('platform/13.2.5/scr');
                return true;
            }))
            ->once();

        Prompt::fake([
            Key::DOWN,
            Key::ENTER,
            'SCOR-980',
            Key::ENTER,
            '27469',
            Key::ENTER,
        ]);

        $cliService = new CLIService($mockWmt, $mockVcs, $mockCherryPicker);
        $cliService->execute();

        expect(true)->toBeTrue();
    });
});

<?php

use Mu\CherryPicker\Clients\JiraClient;
use Mu\CherryPicker\Workflows\MyMergeRequestsWorkflow;

describe('MyMergeRequestsWorkflow::parseTempo', function () {
    it('parses h.mm values for today, yesterday and the day before', function () {
        expect(MyMergeRequestsWorkflow::parseTempo('0.4 2 0'))->toBe([2400, 7200, 0]);
        expect(MyMergeRequestsWorkflow::parseTempo(' 0.15 '))->toBe([900]);
        expect(MyMergeRequestsWorkflow::parseTempo('1.05 0.59'))->toBe([3900, 3540]);
    });

    it('rejects invalid input', function (string $input) {
        expect(MyMergeRequestsWorkflow::parseTempo($input))->toBeNull();
    })->with(['', '0.6', '0.60', '1 2 3 4', 'abc', '1,5', '0.123']);
});

describe('JiraClient::toAdf', function () {
    it('turns known @names into mentions and lines into paragraphs', function () {
        $adf = JiraClient::toAdf("Ready.\njfyi @TESTERNAME1 @Bob", ['TESTERNAME1' => 'acc-1']);

        expect($adf['content'])->toBe([
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Ready.']]],
            ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'jfyi '],
                ['type' => 'mention', 'attrs' => ['id' => 'acc-1', 'text' => '@TESTERNAME1']],
                ['type' => 'text', 'text' => ' @Bob'],
            ]],
        ]);
    });
});

describe('MyMergeRequestsWorkflow::workdaysAgo', function () {
    it('skips weekends', function () {
        // 2026-10-26 is a Monday
        expect(MyMergeRequestsWorkflow::workdaysAgo(0, '2026-10-26'))->toBe('2026-10-26');
        expect(MyMergeRequestsWorkflow::workdaysAgo(1, '2026-10-26'))->toBe('2026-10-23');
        expect(MyMergeRequestsWorkflow::workdaysAgo(2, '2026-10-26'))->toBe('2026-10-22');
        expect(MyMergeRequestsWorkflow::workdaysAgo(2, '2026-10-22'))->toBe('2026-10-20');
    });
});

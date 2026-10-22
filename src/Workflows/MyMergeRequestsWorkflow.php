<?php

namespace Mu\CherryPicker\Workflows;

use Mu\CherryPicker\Clients\GitLabClient;
use Mu\CherryPicker\Clients\JiraClient;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\text;
use function Laravel\Prompts\textarea;
use function Laravel\Prompts\warning;

class MyMergeRequestsWorkflow
{
    private const ICONS = ['success' => '✅', 'failed' => '❌', 'running' => '🔄', 'pending' => '⏳'];
    private const READY_STATUS = 'Ready for testing';
    private const HIDDEN_STATUSES = ['ready for testing', 'done', 'testing'];

    public function __construct(
        private GitLabClient $gitlab,
        private JiraClient $jira,
    ) {}

    public function execute(): void
    {
        [$mrs, $merged] = spin(fn () => [
            $this->gitlab->getAssignedMergeRequests(),
            $this->getMergedPendingTesting(),
        ], 'Loading merge requests...');

        if (!$mrs && !$merged) {
            info('No open or recently merged merge requests assigned to you.');
            return;
        }

        $options = [];
        foreach ($mrs as $key => $mr) {
            $status = $mr['pipeline'] ?? 'no pipeline';
            $icon = self::ICONS[$status] ?? '❔';
            $options["mr$key"] = "$icon $status | Jenkins! x{$mr['jenkins']} | !{$mr['iid']} {$mr['title']}";
        }
        foreach ($merged as $key => $mr) {
            $options["merged$key"] = '🟣 merged ' . date('Y-m-d H:i', strtotime($mr['merged_at'])) . " | {$mr['status']} | !{$mr['iid']} {$mr['title']}";
        }

        $choice = select(label: 'Select merge request', options: $options, scroll: 15);

        if (str_starts_with($choice, 'merged')) {
            $this->handOverToTesting($merged[(int) substr($choice, 6)]);
            return;
        }

        $mr = $mrs[(int) substr($choice, 2)];

        $mention = config('cherry-picker.gitlab.review_mention');
        $review = "$mention {$mr['url']}";

        if ($mr['pipeline'] === 'success') {
            $this->show($review);
            return;
        }

        if ($mr['pipeline'] !== 'failed') {
            warning("Pipeline is '" . ($mr['pipeline'] ?? 'missing') . "', nothing to do yet.");
            return;
        }

        $action = select(label: 'Pipeline failed. What now?', options: [
            'rerun' => 'Comment "Jenkins!" to rerun CI/CD',
            'merge' => 'Ask in Slack to merge (failed randomly)',
        ]);

        if ($action === 'rerun') {
            $this->gitlab->addMergeRequestNote($mr['project_id'], $mr['iid'], 'Jenkins!');
            info('Commented "Jenkins!" on the MR.');
            return;
        }

        $this->show("$review Pipeline failed randomly, can you please merge");
    }

    /**
     * Merged MRs whose Jira ticket (first word of the title) is not yet in testing / done.
     */
    private function getMergedPendingTesting(): array
    {
        $result = [];
        foreach ($this->gitlab->getMergedMergeRequests(config('cherry-picker.gitlab.merged_mr_days')) as $mr) {
            if (!preg_match('/^[A-Z][A-Z0-9]+-\d+/', $mr['title'], $m)) {
                continue;
            }
            $mr['ticket'] = $m[0];
            try {
                $mr['status'] = $this->jira->getStatus($mr['ticket']);
            } catch (\Exception) {
                $mr['status'] = 'jira status unknown';
            }

            if (!in_array(strtolower($mr['status']), self::HIDDEN_STATUSES, true)) {
                $result[] = $mr;
            }
        }

        return $result;
    }

    private function handOverToTesting(array $mr): void
    {
        $ticket = $mr['ticket'];
        $testers = config('cherry-picker.jira.testers');

        try {
            $version = $this->jira->getFixVersion($ticket);
        } catch (\Exception $e) {
            warning($e->getMessage());
            $version = '?';
        }

        $comment = textarea(
            label: "Jira comment for $ticket",
            default: "Ready for testing and deploy in $version version. Update core is required in Jenkins.\njfyi @" . implode(' @', $testers),
            required: true,
        );

        $tester = select(label: 'Assign to', options: array_combine($testers, $testers));

        $tempo = self::parseTempo(text(
            label: 'Tempo hours: today, previous working day, the one before (h.mm, 0.4 = 40 min)',
            placeholder: '0.4 2 0',
            required: true,
            validate: fn (string $v) => self::parseTempo($v) === null ? 'Use 1-3 values like "0.4 2 0", minutes max .59' : null,
        ));

        $this->step("Status -> " . self::READY_STATUS, fn () => $this->jira->transitionTo($ticket, self::READY_STATUS));
        $this->step("Assignee -> $tester", fn () => $this->jira->assign($ticket, $this->jira->findAccountId($tester)));
        $this->step('Comment added', function () use ($ticket, $comment, $testers) {
            $mentions = [];
            foreach ($testers as $name) {
                if (str_contains($comment, "@$name")) {
                    $mentions[$name] = $this->jira->findAccountId($name);
                }
            }
            $this->jira->addComment($ticket, $comment, $mentions);
        });
        $this->step('Tempo logged', function () use ($ticket, $tempo) {
            $me = $this->jira->getMyAccountId();
            foreach ($tempo as $daysAgo => $seconds) {
                if ($seconds > 0) {
                    $this->jira->logTempo($ticket, $me, self::workdaysAgo($daysAgo), $seconds);
                }
            }
        });
    }

    /**
     * "0.4 2 0" -> [today => 2400, previous working day => 7200, the one before => 0] seconds.
     * h.mm format: digits after the dot are minutes, one digit means tens (0.4 = 40 min, 0.15 = 15 min).
     */
    public static function parseTempo(string $input): ?array
    {
        $values = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        if (!$values || count($values) > 3) {
            return null;
        }

        $seconds = [];
        foreach ($values as $value) {
            if (!preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $value, $m)) {
                return null;
            }
            $minutes = (int) str_pad($m[2] ?? '0', 2, '0');
            if ($minutes > 59) {
                return null;
            }
            $seconds[] = (int) $m[1] * 3600 + $minutes * 60;
        }

        return $seconds;
    }

    /** Y-m-d of the working day (Mon-Fri) $n working days before $today; today itself is used as-is. */
    public static function workdaysAgo(int $n, string $today = 'today'): string
    {
        $date = new \DateTimeImmutable($today);
        for ($i = 0; $i < $n; $i++) {
            $date = $date->modify('-1 weekday');
        }

        return $date->format('Y-m-d');
    }

    /** Runs one Jira/Tempo step; a failure is reported but doesn't block the remaining steps. */
    private function step(string $label, callable $action): void
    {
        try {
            $action();
            info("✅ $label");
        } catch (\Throwable $e) {
            warning("❌ $label failed: " . $e->getMessage());
        }
    }

    /** Prints the message and copies it via OSC 52 (supported by most modern terminals, incl. Windows Terminal/WSL). */
    private function show(string $message): void
    {
        note($message);
        echo "]52;c;" . base64_encode($message) . "";
        info('Copied to clipboard (if your terminal supports it) — paste it into Slack.');
    }
}

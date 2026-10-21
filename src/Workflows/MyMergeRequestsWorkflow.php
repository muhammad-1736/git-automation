<?php

namespace Mu\CherryPicker\Workflows;

use Mu\CherryPicker\Clients\GitLabClient;
use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class MyMergeRequestsWorkflow
{
    private const ICONS = ['success' => '✅', 'failed' => '❌', 'running' => '🔄', 'pending' => '⏳'];

    public function __construct(
        private GitLabClient $gitlab,
    ) {}

    public function execute(): void
    {
        $mrs = $this->gitlab->getAssignedMergeRequests();

        if (!$mrs) {
            info('No open merge requests assigned to you.');
            return;
        }

        $options = [];
        foreach ($mrs as $key => $mr) {
            $status = $mr['pipeline'] ?? 'no pipeline';
            $icon = self::ICONS[$status] ?? '❔';
            $options["mr$key"] = "$icon $status | Jenkins! x{$mr['jenkins']} | !{$mr['iid']} {$mr['title']}";
        }

        $mr = $mrs[(int) substr(select(label: 'Select merge request', options: $options, scroll: 15), 2)];

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

    /** Prints the message and copies it via OSC 52 (supported by most modern terminals, incl. Windows Terminal/WSL). */
    private function show(string $message): void
    {
        note($message);
        echo "]52;c;" . base64_encode($message) . "";
        info('Copied to clipboard (if your terminal supports it) — paste it into Slack.');
    }
}

<?php

namespace Mu\CherryPicker;

use Mu\CherryPicker\Contracts\WMTProviderContract;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

class TicketResolver
{
    /**
     * Ask for JIRA ticket and resolve its details.
     *
     * @param WMTProviderContract $wmtProvider
     * @return array{ticketNumber: string, featureOrBug: string, fixVersion: string, titleMR: string}
     */
    public static function resolve(WMTProviderContract $wmtProvider): array
    {
        $ticketNumber = text('Please provide me ticket', 'SOCS-test');

        try {
            $featureOrBug = $wmtProvider->getFeatureOrBug($ticketNumber);
            $fixVersion   = $wmtProvider->getFixVersion($ticketNumber);
            $titleMR      = $ticketNumber . ' ' . $wmtProvider->getSummary($ticketNumber);
        } catch (\Exception $e) {
            warning('Jira access failed (' . $e->getMessage() . '). Falling back to manual input.');

            $issueType = select(
                label: 'What is the issue type?',
                options: [
                    'feature' => 'Feature',
                    'bug'     => 'Bug',
                ],
                default: 'bug'
            );
            $featureOrBug = $issueType;

            $fixVersion = text(
                label: 'Please provide the fixVersion (e.g. 12.0.1)',
                placeholder: '12.0.1',
                required: true
            );

            $summary = text(
                label: 'Please provide the ticket summary (for the Merge Request title)',
                placeholder: 'Fix issue with database connection',
                required: true
            );
            $titleMR = $ticketNumber . ' ' . $summary;
        }

        return [
            'ticketNumber' => $ticketNumber,
            'featureOrBug' => $featureOrBug,
            'fixVersion'   => $fixVersion,
            'titleMR'      => $titleMR,
        ];
    }
}

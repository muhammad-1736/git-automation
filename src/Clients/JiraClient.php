<?php

namespace Mu\CherryPicker\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ClientException;
use Exception;
use Mu\CherryPicker\Contracts\WMTProviderContract;

use function Laravel\Prompts\info;

class JiraClient implements WMTProviderContract
{
    private array $localCache = [];
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    /**
     * Get title - summary of issue
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Exception
     */
    public function getSummary(string $ticketNumber): string
    {
        return $this->getDataSafe($ticketNumber, 'fields.summary', 'How it is even possible!!! Ticket doesnt have TITLE. Maybe Jira REST API was changed');
    }

    /**
     * Get IssueType of Issue
     *
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Exception
     */
    public function getFeatureOrBug(string $ticketNumber): string
    {
        $type = $this->getDataSafe($ticketNumber, 'fields.issuetype.name', 'This guy (I mean ticket) doesnt have Issue Type. Or maybe Jira Api was changed (less likely but double check their API release notes if ticket has Issue type)');

        return match ($type) {
            'Task' => 'feature',
            'Bug'  => 'bugfix',
            default => throw new Exception("Unknown issue type: {$type}. Is it feature or bug? (If you know, then just hardcode by yourself)"),
        };
    }

    /**
     * @throws \Illuminate\Http\Client\ConnectionException
     * @throws \Exception
     */
    public function getFixVersion(string $ticketNumber): string
    {
        $fixVersionName = $this->getDataSafe($ticketNumber, 'fields.fixVersions.0.name', 'No fix version found in Ticket. Or maybe Jira Api was changed (less likely but double check their API release notes if ticket has Fix version)');

        // The pattern now strictly requires a dot followed by digits (\.\d+)
        if (preg_match_all('#(/)?(\d+\.\d+(?:\.\d+)*)#', $fixVersionName, $matches, PREG_SET_ORDER)) {
            $count = count($matches);
            $version = null;

            if ($count === 1) {
                // Rule 1: If exactly 1 occurrence exists, take it
                $version = $matches[0][2];
            } elseif ($count === 2) {
                // Rule 2: If 2 occurrences exist, get the one prefixed with '/'
                $firstHasSlash  = !empty($matches[0][1]);
                $secondHasSlash = !empty($matches[1][1]);

                if ($firstHasSlash && !$secondHasSlash) {
                    $version = $matches[0][2];
                } elseif (!$firstHasSlash && $secondHasSlash) {
                    $version = $matches[1][2];
                } else {
                    // Ambiguous: either both have a slash or neither does
                    throw new \Exception('Fix version format is invalid.');
                }
            } else {
                // Rule 3: 0 or more than 2 occurrences throw an exception
                throw new \Exception('Fix version format is invalid.');
            }

            // Convert and normalize (e.g., 12.2 -> 12.2.0 ; 12.2.0 -> 12.2.0)
            $parts = explode('.', $version);
            $normalizedParts = array_pad($parts, 3, '0');

            $normalized_version = implode('.', $normalizedParts);

            info("version is $normalized_version");

            return $normalized_version;
        }

        // Throws exception if no valid version formats (X.Y or X.Y.Z) were found
        throw new \Exception('Fix version format is invalid.');
    }

    public function getStatus(string $ticketNumber): string
    {
        return $this->getDataSafe($ticketNumber, 'fields.status.name', "Ticket $ticketNumber has no status");
    }

    /**
     * Moves the issue through the transition leading to $statusName.
     *
     * @throws GuzzleException
     */
    public function transitionTo(string $ticketNumber, string $statusName): void
    {
        $transitions = $this->api('GET', "/issue/$ticketNumber/transitions")['transitions'];

        foreach ($transitions as $transition) {
            if (strcasecmp($transition['to']['name'], $statusName) === 0 || strcasecmp($transition['name'], $statusName) === 0) {
                $this->api('POST', "/issue/$ticketNumber/transitions", ['transition' => ['id' => $transition['id']]]);
                return;
            }
        }

        throw new Exception("No transition to '$statusName' available for $ticketNumber");
    }

    /**
     * @throws GuzzleException
     */
    public function assign(string $ticketNumber, string $accountId): void
    {
        $this->api('PUT', "/issue/$ticketNumber/assignee", ['accountId' => $accountId]);
    }

    /**
     * Adds a comment; every "@Name" from $mentions (name => accountId) becomes a real Jira mention.
     *
     * @throws GuzzleException
     */
    public function addComment(string $ticketNumber, string $text, array $mentions = []): void
    {
        $this->api('POST', "/issue/$ticketNumber/comment", ['body' => self::toAdf($text, $mentions)]);
    }

    /**
     * Plain text -> Atlassian Document Format (one paragraph per line).
     */
    public static function toAdf(string $text, array $mentions = []): array
    {
        $pattern = $mentions
            ? '/(@(?:' . implode('|', array_map(fn ($n) => preg_quote($n, '/'), array_keys($mentions))) . '))/'
            : '/(?!)/';

        $paragraphs = [];
        foreach (explode("\n", $text) as $line) {
            $content = [];
            foreach (preg_split($pattern, $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $part) {
                $name = substr($part, 1);
                $content[] = $part[0] === '@' && isset($mentions[$name])
                    ? ['type' => 'mention', 'attrs' => ['id' => $mentions[$name], 'text' => $part]]
                    : ['type' => 'text', 'text' => $part];
            }
            $paragraphs[] = ['type' => 'paragraph', 'content' => $content];
        }

        return ['type' => 'doc', 'version' => 1, 'content' => $paragraphs];
    }

    /**
     * @throws GuzzleException
     */
    public function findAccountId(string $displayName): string
    {
        foreach ($this->api('GET', '/user/search?query=' . rawurlencode($displayName)) as $user) {
            if (strcasecmp($user['displayName'], $displayName) === 0) {
                return $user['accountId'];
            }
        }

        throw new Exception("Jira user '$displayName' not found");
    }

    /**
     * @throws GuzzleException
     */
    public function getMyAccountId(): string
    {
        return $this->api('GET', '/myself')['accountId'];
    }

    /**
     * Logs time in Tempo (Tempo Cloud REST API v4, needs the numeric Jira issue id).
     *
     * @throws GuzzleException
     */
    public function logTempo(string $ticketNumber, string $accountId, string $date, int $seconds): void
    {
        $this->client->post(config('cherry-picker.tempo.api_url') . '/worklogs', [
            'headers' => ['Authorization' => 'Bearer ' . config('cherry-picker.tempo.token')],
            'json' => [
                'issueId' => (int) $this->getDataSafe($ticketNumber, 'id', "Ticket $ticketNumber has no id"),
                'authorAccountId' => $accountId,
                'startDate' => $date,
                'timeSpentSeconds' => $seconds,
                'description' => "Working on issue $ticketNumber",
                'attributes' => [
                    ['key' => '_Activity_', 'value' => config('cherry-picker.tempo.activity')],
                    ['key' => '_Timetype_', 'value' => config('cherry-picker.tempo.time_type')],
                ],
            ],
        ]);
    }

    /**
     * @throws GuzzleException
     */
    private function api(string $method, string $path, ?array $json = null): ?array
    {
        $response = $this->client->request($method, config('cherry-picker.jira.api_url') . $path, array_filter([
            'auth' => [config('cherry-picker.jira.email'), config('cherry-picker.jira.personal_access_token')],
            'json' => $json,
        ]));

        return json_decode($response->getBody()->getContents(), true);
    }

    /**
     * @param string $ticketNumber
     * @param string $property
     * @param string $errorMessage
     *
     * @return array|mixed
     * @throws \Illuminate\Http\Client\ConnectionException
     */
    private function getDataSafe(string $ticketNumber, string $property, string $errorMessage): mixed
    {
        $data    = $this->getIssueDetails($ticketNumber);
        $issueProperty = data_get($data, $property);

        if (!$issueProperty) {
            throw new Exception($errorMessage);
        }

        return $issueProperty;
    }

    /**
     * @throws GuzzleException
     * @throws \Exception
     */
    private function getIssueDetails(string $ticketNumber): array
    {
        // Only fetch if we haven't fetched this ticket yet
        if (!isset($this->localCache[$ticketNumber])) {
            try {
                $response = $this->client->get(
                    config('cherry-picker.jira.api_url') . "/issue/{$ticketNumber}",
                    [
                        'auth' => [
                            config('cherry-picker.jira.email'),
                            config('cherry-picker.jira.personal_access_token'),
                        ],
                    ]
                );

                $this->localCache[$ticketNumber] = json_decode($response->getBody()->getContents(), true);
            } catch (ClientException $e) {
                $statusCode = $e->getResponse()->getStatusCode();
                if ($statusCode === 404 || $statusCode === 403) {
                    throw new Exception("Jira Ticket {$ticketNumber} not found or you dont have permissions or wrong configurations or maybe Jira Api was changed");
                }
                throw $e;
            }
        }

        return $this->localCache[$ticketNumber];
    }
}

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

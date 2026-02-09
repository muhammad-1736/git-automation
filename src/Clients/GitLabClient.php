<?php

namespace Mu\CherryPicker\Clients;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Mu\CherryPicker\Contracts\VCSProviderContract;
use Mu\CherryPicker\Config\ConfigLoader;
use function Laravel\Prompts\warning;

class GitLabClient implements VCSProviderContract
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client([
            'verify' => false,
        ]);
    }
    public function createMergeRequest(
        string $projectGroup,
        string $sourceBranch,
        string $targetBranch,
        string $title,
        ?string $description = null,
        ?string $assigneeName = null,
        ?string $reviewerName = null
    ): void {
        $projectId = $this->getProjectIdByGroup($projectGroup);

        // Get assignee ID if provided
        $assigneeId = null;
        if ($assigneeName) {
            $assigneeId = $this->getUserIdByName($assigneeName);
            if (! $assigneeId) {
                warning("Assignee '$assigneeName' not found in GitLab");
            }
        }

        // Get reviewer ID if provided
        $reviewerId = null;
        if ($reviewerName) {
            $reviewerId = $this->getUserIdByName($reviewerName);
            if (!$reviewerId) {
                warning("Reviewer '$reviewerName' not found in GitLab");
            }
        }

        $apiBaseUrl = config('cherry-picker.gitlab.api_url');

        // Create merge request
        try {
            $response = $this->client->post("$apiBaseUrl/projects/$projectId/merge_requests", [
                'headers' => [
                    'PRIVATE-TOKEN' => $this->getPAT(),
                ],
                'json' => [
                    'source_branch' => $sourceBranch,
                    'target_branch' => $targetBranch,
                    'title' => $title,
                    'description' => $description,
                    'remove_source_branch' => true,
                    'assignee_id' => $assigneeId,
                    'reviewer_ids' => [$reviewerId],
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                warning('Failed to create GitLab merge request, make it manually');
            }
        } catch (GuzzleException $e) {
            warning('Failed to create GitLab merge request, make it manually, Reason: ' . $e->getMessage() . PHP_EOL . PHP_EOL . ' It is the trace where error happened:' . PHP_EOL . $e->getTraceAsString());
        }
    }

    public function getPAT(): string
    {
        return config('cherry-picker.gitlab.personal_access_token');
    }

    /**
     * Get project ID by project name
     *
     * @param string $groupName
     * @param string $projectName
     *
     * @return int|null
     * @throws GuzzleException
     */
    public function getProjectIdByGroup(string $groupName, string $projectName = 'platform'): ?int
    {
        try {
            $response = $this->client->get(config('cherry-picker.gitlab.api_url')."/groups", [
                'headers' => [
                    'PRIVATE-TOKEN' => $this->getPAT(),
                ],
                'query' => [
                    'search' => $groupName,
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true);
            $group_id = $data[0]['id'] ?? null;

            if (!isset($group_id)) {
                warning('Failed to get Group ID');
                exit(1);
            }

            $response = $this->client->get(config('cherry-picker.gitlab.api_url')."/groups/$group_id/projects", [
                'headers' => [
                    'PRIVATE-TOKEN' => $this->getPAT(),
                ],
                'query' => [
                    'search' => $projectName,
                ],
            ]);

            $projects = json_decode($response->getBody()->getContents(), true);
            foreach ($projects as $project) {
                if ($project['path_with_namespace'] === "$groupName/$projectName") {
                    return $project['id'];
                }
            }

            warning('Failed to get Project ID');
            exit(1);
        } catch (GuzzleException $e) {
            warning('Failed to get Project ID, reason: ' . $e->getMessage() . PHP_EOL . PHP_EOL . ' It is the trace where error happened:' . PHP_EOL . $e->getTraceAsString());
            exit(1);
        }
    }

    /**
     * Get user ID by name
     *
     * @param string $name
     *
     * @return int|null
     * @throws GuzzleException
     */
    public function getUserIdByName(string $name): ?int
    {
        try {
            $response = $this->client->get(config('cherry-picker.gitlab.api_url')."/users", [
                'headers' => [
                    'PRIVATE-TOKEN' => $this->getPAT(),
                ],
                'query' => [
                    'username' => $name,
                ],
            ]);

            $users = json_decode($response->getBody()->getContents(), true);
            if (empty($users)) {
                warning('Failed to get User ID, reason: No users found, will skip assignee/reviewer');
                return null;
            }

            return $users[0]['id'];
        } catch (GuzzleException $e) {
            warning('Failed to get User ID, reason: ' . $e->getMessage());
            warning('Will skip assignee/reviewer');
            return null;
        }
    }

    /**
     * @param $mrIid
     *
     * @return string
     * @throws GuzzleException
     * @throws \Exception
     */
    public function getMergeRequestCommits($mrIid): string
    {
        $projectId = $this->getProjectIdByGroup('px');

        try {
            $response = $this->client->get(
                config('cherry-picker.gitlab.api_url') . "/projects/$projectId/merge_requests/$mrIid/commits",
                [
                    'headers' => [
                        'PRIVATE-TOKEN' => $this->getPAT(),
                    ],
                ]
            );

            $commits = '';
            $data = json_decode($response->getBody()->getContents(), true);
            foreach ($data as $commit) {
                $commits .= ' '.$commit['id'];
            }

            if ($commits === '') throw new \Exception('No commits found', 404);

            return trim($commits);
        } catch (GuzzleException $e) {
            throw $e;
        }
    }
}
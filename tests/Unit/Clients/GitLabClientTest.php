<?php

use Mu\CherryPicker\Clients\GitLabClient;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

describe('GitLabClient', function () {
    describe('getPAT', function () {
        it('returns the personal access token', function () {
            $gitlabClient = new GitLabClient();
            $token = $gitlabClient->getPAT();

            expect($token)->toBe('test-gitlab-token-123');
        });
    });

    describe('createMergeRequest', function () {
        it('creates a merge request successfully', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            // Mock getProjectIdByGroup response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 1, 'name' => 'test-group'],
                ]));

            // Mock get projects response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups/1/projects', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 101, 'path_with_namespace' => 'test-group/platform'],
                ]));

            // Mock get user response for assignee
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/users', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 201, 'username' => 'john_doe'],
                ]));

            // Mock create merge request
            $mockGuzzleClient->shouldReceive('post')
                ->andReturn(createMockResponse());

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            // Execute the method - should not throw exception
            expect(fn () => $gitlabClient->createMergeRequest(
                'test-group',
                'feature/test',
                'main',
                'Test MR',
                'Test Description',
                'john_doe',
                'jane_doe'
            ))->not->toThrow(\Exception::class);
        });

        it('creates merge request without assignee and reviewer', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 1, 'name' => 'test-group'],
                ]));

            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups/1/projects', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 101, 'path_with_namespace' => 'test-group/platform'],
                ]));

            $mockGuzzleClient->shouldReceive('post')
                ->andReturn(createMockResponse());

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            // Execute the method - should not throw exception
            expect(fn () => $gitlabClient->createMergeRequest(
                'test-group',
                'feature/test',
                'main',
                'Test MR'
            ))->not->toThrow(\Exception::class);
        });
    });

    describe('getProjectIdByGroup', function () {
        it('returns project id by group name', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 42, 'name' => 'test-group'],
                ]));

            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups/42/projects', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 123, 'path_with_namespace' => 'test-group/platform'],
                    ['id' => 456, 'path_with_namespace' => 'test-group/other'],
                ]));

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            $projectId = $gitlabClient->getProjectIdByGroup('test-group');

            expect($projectId)->toBe(123);
        });
    });

    describe('getUserIdByName', function () {
        it('returns user id by username', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/users', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 789, 'username' => 'john_doe', 'name' => 'John Doe'],
                ]));

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            $userId = $gitlabClient->getUserIdByName('john_doe');

            expect($userId)->toBe(789);
        });

        it('returns null when user not found', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/users', \Mockery::any())
                ->andReturn(createGitlabMockResponse([]));

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            $userId = $gitlabClient->getUserIdByName('non_existent_user');

            expect($userId)->toBeNull();
        });

        it('returns null on guzzle exception', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/users', \Mockery::any())
                ->andThrow(new \GuzzleHttp\Exception\RequestException('Network error', \Mockery::mock(\Psr\Http\Message\RequestInterface::class)));

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            $userId = $gitlabClient->getUserIdByName('john_doe');

            expect($userId)->toBeNull();
        });
    });

    describe('getMergeRequestCommits', function () {
        it('returns space-separated commit ids', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            // Mock getProjectIdByGroup response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 1, 'name' => 'px'],
                ]));

            // Mock get projects response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups/1/projects', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 101, 'path_with_namespace' => 'px/platform'],
                ]));

            // Mock get merge request commits response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/projects/101/merge_requests/1/commits', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 'abc123def456'],
                    ['id' => 'xyz789uvw123'],
                    ['id' => 'qwe456rty789'],
                ]));

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            $commits = $gitlabClient->getMergeRequestCommits(1);

            expect($commits)->toBe('abc123def456 xyz789uvw123 qwe456rty789');
        });

        it('throws exception when no commits found', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            // Mock getProjectIdByGroup response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 1, 'name' => 'px'],
                ]));

            // Mock get projects response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups/1/projects', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 101, 'path_with_namespace' => 'px/platform'],
                ]));

            // Mock get merge request commits response (empty)
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/projects/101/merge_requests/1/commits', \Mockery::any())
                ->andReturn(createGitlabMockResponse([]));

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            expect(fn () => $gitlabClient->getMergeRequestCommits(1))
                ->toThrow(\Exception::class, 'No commits found');
        });

        it('throws guzzle exception on network error', function () {
            $mockGuzzleClient = \Mockery::mock(Client::class);

            // Mock getProjectIdByGroup response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 1, 'name' => 'px'],
                ]));

            // Mock get projects response
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/groups/1/projects', \Mockery::any())
                ->andReturn(createGitlabMockResponse([
                    ['id' => 101, 'path_with_namespace' => 'px/platform'],
                ]));

            // Mock network error
            $mockGuzzleClient->shouldReceive('get')
                ->with('https://gitlab.example.com/api/v4/projects/101/merge_requests/1/commits', \Mockery::any())
                ->andThrow(new \Exception('Network error'));

            $gitlabClient = new GitLabClient();
            $reflection = new ReflectionClass($gitlabClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($gitlabClient, $mockGuzzleClient);

            expect(fn () => $gitlabClient->getMergeRequestCommits(1))
                ->toThrow(\Exception::class);
        });
    });
});

// Helper function for tests
function createGitlabMockResponse(array $data) {
    $mockResponse = \Mockery::mock(ResponseInterface::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode($data));

    return $mockResponse;
}

function createMockResponse() {
    $mockResponse = \Mockery::mock(ResponseInterface::class);
    $mockResponse->shouldReceive('getStatusCode')->andReturn(200);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode([]));

    return $mockResponse;
}

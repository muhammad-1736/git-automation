<?php

use Mu\CherryPicker\Clients\JiraClient;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;

describe('JiraClient', function () {
    describe('getSummary', function () {
        it('retrieves issue summary successfully', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Issue Summary',
                    'issuetype' => ['name' => 'Task'],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            $summary = $jiraClient->getSummary('JIRA-123');

            expect($summary)->toBe('Test Issue Summary');
        });

        it('throws exception when summary is missing', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'issuetype' => ['name' => 'Task'],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            expect(fn () => $jiraClient->getSummary('JIRA-123'))
                ->toThrow(\Exception::class, 'doesnt have TITLE');
        });
    });

    describe('getFeatureOrBug', function () {

        it('returns bug for Bug issue type', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                    'issuetype' => ['name' => 'Bug'],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            $type = $jiraClient->getFeatureOrBug('JIRA-123');

            expect($type)->toBe('bug');
        });

        it('throws exception for unknown issue type', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                    'issuetype' => ['name' => 'Epic'],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            expect(fn () => $jiraClient->getFeatureOrBug('JIRA-123'))
                ->toThrow(\Exception::class, 'Unknown issue type: Epic');
        });

        it('throws exception when issuetype is missing', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            expect(fn () => $jiraClient->getFeatureOrBug('JIRA-123'))
                ->toThrow(\Exception::class, 'doesnt have Issue Type');
        });
    });

    describe('getFixVersion', function () {
        it('retrieves and normalizes single digit version', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                    'issuetype' => ['name' => 'Task'],
                    'fixVersions' => [
                        ['name' => 'v2'],
                    ],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            $version = $jiraClient->getFixVersion('JIRA-123');

            expect($version)->toBe('2.0.0');
        });

        it('retrieves and normalizes two digit version', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                    'issuetype' => ['name' => 'Task'],
                    'fixVersions' => [
                        ['name' => 'v2.1'],
                    ],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            $version = $jiraClient->getFixVersion('JIRA-123');

            expect($version)->toBe('2.1.0');
        });

        it('retrieves and normalizes three digit version', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                    'issuetype' => ['name' => 'Task'],
                    'fixVersions' => [
                        ['name' => 'v2.1.5'],
                    ],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            $version = $jiraClient->getFixVersion('JIRA-123');

            expect($version)->toBe('2.1.5');
        });

        it('throws exception when fixVersions is empty', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                    'issuetype' => ['name' => 'Task'],
                    'fixVersions' => [],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            expect(fn () => $jiraClient->getFixVersion('JIRA-123'))
                ->toThrow(\Exception::class, 'No fix version found');
        });

        it('throws exception for invalid version format', function () {
            $mockClient = createMockGuzzleClient([
                'fields' => [
                    'summary' => 'Test Summary',
                    'issuetype' => ['name' => 'Task'],
                    'fixVersions' => [
                        ['name' => 'invalid-version-name'],
                    ],
                ],
            ]);

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockClient);

            expect(fn () => $jiraClient->getFixVersion('JIRA-123'))
                ->toThrow(\Exception::class, 'Fix version format is invalid');
        });
    });

    describe('HTTP error handling', function () {
        it('throws exception on 404 response', function () {
            $mockRequest = \Mockery::mock(\Psr\Http\Message\RequestInterface::class);
            $mockResponse = \Mockery::mock(ResponseInterface::class);
            $mockResponse->shouldReceive('getStatusCode')->andReturn(404);

            $mockGuzzleClient = \Mockery::mock(Client::class);
            $mockGuzzleClient->shouldReceive('get')
                ->andThrow(new ClientException('Not Found', $mockRequest, $mockResponse));

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockGuzzleClient);

            expect(fn () => $jiraClient->getSummary('INVALID-999'))
                ->toThrow(\Exception::class, 'Jira Ticket');
        });

        it('throws exception on 403 response', function () {
            $mockRequest = \Mockery::mock(\Psr\Http\Message\RequestInterface::class);
            $mockResponse = \Mockery::mock(ResponseInterface::class);
            $mockResponse->shouldReceive('getStatusCode')->andReturn(403);

            $mockGuzzleClient = \Mockery::mock(Client::class);
            $mockGuzzleClient->shouldReceive('get')
                ->andThrow(new ClientException('Forbidden', $mockRequest, $mockResponse));

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockGuzzleClient);

            expect(fn () => $jiraClient->getSummary('JIRA-123'))
                ->toThrow(\Exception::class, 'dont have permissions');
        });
    });

    describe('Local caching', function () {
        it('caches issue details to avoid multiple requests', function () {
            // To actually validate caching, set up a counter to track "get" calls
            $getCallCount = 0;
            $mockGuzzleClient = \Mockery::mock(Client::class);
            $mockGuzzleClient->shouldReceive('get')
                ->andReturnUsing(function () use (&$getCallCount) {
                    $getCallCount++;
                    return createJiraMockResponse([
                        'fields' => [
                            'summary' => 'Test Summary',
                            'issuetype' => ['name' => 'Task'],
                        ],
                    ]);
                });

            $jiraClient = new JiraClient();
            $reflection = new ReflectionClass($jiraClient);
            $property = $reflection->getProperty('client');
            $property->setAccessible(true);
            $property->setValue($jiraClient, $mockGuzzleClient);

            // First call should make HTTP request and second call should use cache (no additional HTTP request!)
            $summary1 = $jiraClient->getSummary('JIRA-123');
            $summary2 = $jiraClient->getSummary('JIRA-123');
            $summary3 = $jiraClient->getSummary('JIRA-123');
            $summary4 = $jiraClient->getSummary('JIRA-123');

            expect($summary1)->toBe('Test Summary');
            expect($summary2)->toBe('Test Summary');
            expect($summary3)->toBe('Test Summary');
            expect($summary4)->toBe('Test Summary');
            expect($getCallCount)->toBe(1); 
        });
    });
});

// Helper functions for tests
function createMockGuzzleClient(array $responseData) {
    $mockGuzzleClient = \Mockery::mock(Client::class);
    $mockGuzzleClient->shouldReceive('get')
        ->andReturn(createJiraMockResponse($responseData));

    return $mockGuzzleClient;
}

function createJiraMockResponse(array $data) {
    $mockResponse = \Mockery::mock(ResponseInterface::class);
    $mockResponse->shouldReceive('getBody->getContents')
        ->andReturn(json_encode($data));

    return $mockResponse;
}

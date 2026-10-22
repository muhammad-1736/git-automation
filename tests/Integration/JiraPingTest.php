<?php

use GuzzleHttp\Client;
use Mu\CherryPicker\Container;

// Real network call using the real config (.env in project root).
// Run: vendor/bin/pest --testsuite Integration
it('pings the real Jira API and authenticates', function () {
    global $container;
    $original  = $container;
    $container = new Container(dirname(__DIR__, 2)); // loads .env + src/Config, same as bin/cherry-picker

    try {
        $url   = config('cherry-picker.jira.api_url');
        $email = config('cherry-picker.jira.email');
        $token = config('cherry-picker.jira.personal_access_token');

        if (!$url || !$email || !$token) {
            $this->markTestSkipped('Jira is not configured in .env (JIRA_API_URL, JIRA_EMAIL, JIRA_TOKEN).');
        }

        $response = (new Client(['timeout' => 8]))->get(rtrim($url, '/') . '/myself', [
            'auth' => [$email, $token],
        ]);

        expect($response->getStatusCode())->toBe(200);
        expect(json_decode($response->getBody()->getContents(), true))->toHaveKey('accountId');
    } finally {
        $container = $original;
    }
});

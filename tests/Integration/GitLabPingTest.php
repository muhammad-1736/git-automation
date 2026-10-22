<?php

use GuzzleHttp\Client;
use Mu\CherryPicker\Container;

// Real network call using the real config (.env in project root).
// Run: vendor/bin/pest --testsuite Integration
it('pings the real GitLab API and authenticates', function () {
    global $container;
    $original  = $container;
    $container = new Container(dirname(__DIR__, 2)); // loads .env + src/Config, same as bin/cherry-picker

    try {
        $url   = config('cherry-picker.gitlab.api_url');
        $token = config('cherry-picker.gitlab.personal_access_token');

        if (!$url || !$token) {
            $this->markTestSkipped('GitLab is not configured in .env (GITLAB_API_URL, PERSONAL_TOKEN).');
        }

        // verify=false mirrors GitLabClient (self-hosted GitLab)
        $response = (new Client(['timeout' => 8, 'verify' => false]))->get(rtrim($url, '/') . '/user', [
            'headers' => ['PRIVATE-TOKEN' => $token],
        ]);

        expect($response->getStatusCode())->toBe(200);
        expect(json_decode($response->getBody()->getContents(), true))->toHaveKey('username');
    } finally {
        $container = $original;
    }
});

<?php

use GuzzleHttp\Client;
use Mu\CherryPicker\Container;

// Real network call using the real config (.env in project root). Read-only: never creates worklogs.
// Run: vendor/bin/pest --testsuite Integration
it('pings the real Tempo API and authenticates', function () {
    global $container;
    $original  = $container;
    $container = new Container(dirname(__DIR__, 2)); // loads .env + src/Config, same as bin/cherry-picker

    try {
        $url   = config('cherry-picker.tempo.api_url');
        $token = config('cherry-picker.tempo.token');

        if (!$url || !$token) {
            $this->markTestSkipped('Tempo is not configured in .env (TEMPO_API_URL, TEMPO_TOKEN).');
        }

        $today    = date('Y-m-d');
        $response = (new Client(['timeout' => 8]))->get(rtrim($url, '/') . '/worklogs', [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'query'   => ['from' => $today, 'to' => $today, 'limit' => 1],
        ]);

        expect($response->getStatusCode())->toBe(200);
        expect(json_decode($response->getBody()->getContents(), true))->toHaveKey('results');
    } finally {
        $container = $original;
    }
});

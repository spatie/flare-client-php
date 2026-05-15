<?php

use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Http\Response;

it('accepts created responses as successful', function () {
    $client = new class extends Client {
        public function makeCurlRequest(string $httpVerb, string $fullUrl, array $headers = [], array $arguments = []): Response
        {
            return new Response(['http_code' => 201], [], '');
        }
    };

    expect($client->post('reports', []))->toBe([]);
});

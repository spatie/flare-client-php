<?php

namespace Spatie\FlareClient\Senders;

use GuzzleHttp\Client;

class GuzzleSender implements Sender
{
    protected Client $client;

    public function __construct(
        protected array $config = []
    ) {
        $this->client = new Client($config);
    }

    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        $response = $this->client->post($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-token' => $apiToken,
            ],
            'json' => $payload,
        ]);

        if ($response->getStatusCode() !== 200) {
            return [];
        }

        return json_decode($response->getBody()->getContents(), true);
    }
}

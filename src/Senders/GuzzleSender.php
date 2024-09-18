<?php

namespace Spatie\FlareClient\Senders;

use GuzzleHttp\Client;
use Spatie\FlareClient\Senders\Support\Response;

class GuzzleSender implements Sender
{
    protected Client $client;

    public function __construct(
        protected array $config = []
    ) {
        $this->client = new Client($config);
    }

    public function post(string $endpoint, string $apiToken, array $payload): Response
    {
        $response = $this->client->post($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-token' => $apiToken,
            ],
            'json' => $payload,
        ]);

        return new Response(
            $response->getStatusCode(),
            json_decode($response->getBody()->getContents(), true),
        );
    }
}

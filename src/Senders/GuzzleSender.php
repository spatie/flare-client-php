<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use GuzzleHttp\Client;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\Exceptions\ConnectionError;
use Spatie\FlareClient\Senders\Support\Response;

class GuzzleSender implements Sender
{
    protected Client $client;

    public function __construct(
        protected array $config = []
    ) {
        $this->client = new Client($config);
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        $response = $this->client->post($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-token' => $apiToken,
            ],
            'json' => $payload,
        ]);

        $rawResponse = $response->getBody()->getContents();

        $body = json_decode($rawResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConnectionError('Invalid JSON response received');
        }

        $callback(new Response(
            $response->getStatusCode(),
            $body,
        ));
    }
}

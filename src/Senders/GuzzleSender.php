<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Support\Response;

class GuzzleSender implements Sender
{
    protected Client $client;

    public function __construct(
        protected array $config = []
    ) {
        $this->client = new Client($config);
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlarePayloadType $type, Closure $callback): void
    {
        $response = $this->executeRequest($endpoint, $apiToken, $payload);

        $rawResponse = $response->getBody()->getContents();

        $body = json_decode($rawResponse, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $body = $rawResponse;
        }

        $callback(new Response(
            $response->getStatusCode(),
            $body,
        ));
    }

    protected function executeRequest(string $endpoint, string $apiToken, array $payload): ResponseInterface
    {
        return $this->client->post($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-token' => $apiToken,
            ],
            'json' => $payload,
        ]);
    }
}

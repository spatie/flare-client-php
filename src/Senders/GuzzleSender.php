<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use GuzzleHttp\Client;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Support\JsonEncodableSanitizer;
use Spatie\FlareClient\Senders\Support\Response;

class GuzzleSender extends AbstractSender
{
    protected Client $client;

    public function __construct(
        array $config = [],
        PayloadSanitizer $sanitizer = new JsonEncodableSanitizer
    ) {
        parent::__construct($config, $sanitizer);
        $this->client = new Client($config);
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlarePayloadType $type, Closure $callback): void
    {
        $response = $this->client->post($endpoint, [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'x-api-token' => $apiToken,
            ],
            'json' => $this->preparePayloadForEncoding($payload),
        ]);

        $callback(new Response(
            $response->getStatusCode(),
            json_decode($response->getBody()->getContents(), true),
        ));
    }
}

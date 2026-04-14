<?php

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Psr\Http\Message\ResponseInterface;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\GuzzleSender;
use Spatie\FlareClient\Senders\Support\Response;

class TestGuzzleSender extends GuzzleSender
{
    public GuzzleResponse $fakeResponse;

    public function __construct()
    {
        parent::__construct();

        $this->fakeResponse = new GuzzleResponse(200, [], '');
    }

    protected function executeRequest(string $endpoint, string $apiToken, array $payload): ResponseInterface
    {
        return $this->fakeResponse;
    }
}

it('does not throw when receiving an empty response body', function () {
    $sender = new TestGuzzleSender();
    $sender->fakeResponse = new GuzzleResponse(200, [], '');

    $response = null;

    $sender->post(
        'https://example.com/api',
        'fake-api-key',
        ['test' => 'payload'],
        FlarePayloadType::Error,
        function (Response $r) use (&$response) {
            $response = $r;
        }
    );

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->body)->toBe('');
});

it('does not throw when receiving a non-JSON response body', function () {
    $sender = new TestGuzzleSender();
    $sender->fakeResponse = new GuzzleResponse(200, [], 'OK');

    $response = null;

    $sender->post(
        'https://example.com/api',
        'fake-api-key',
        ['test' => 'payload'],
        FlarePayloadType::Error,
        function (Response $r) use (&$response) {
            $response = $r;
        }
    );

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->body)->toBe('OK');
});

it('still parses valid JSON responses into arrays', function () {
    $sender = new TestGuzzleSender();
    $sender->fakeResponse = new GuzzleResponse(200, [], '{"message":"success"}');

    $response = null;

    $sender->post(
        'https://example.com/api',
        'fake-api-key',
        ['test' => 'payload'],
        FlarePayloadType::Error,
        function (Response $r) use (&$response) {
            $response = $r;
        }
    );

    expect($response)->toBeInstanceOf(Response::class);
    expect($response->body)->toBe(['message' => 'success']);
});

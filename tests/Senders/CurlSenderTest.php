<?php

use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Exceptions\ConnectionError;
use Spatie\FlareClient\Senders\Support\Response;

class TestCurlSender extends CurlSender
{
    public string|bool $fakeResponse = '';

    protected function executeCurl(CurlHandle $curlHandle): string|bool
    {
        return $this->fakeResponse;
    }
}

it('does not throw when receiving an empty response body', function () {
    $sender = new TestCurlSender();
    $sender->fakeResponse = '';

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
    $sender = new TestCurlSender();
    $sender->fakeResponse = 'OK';

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
    $sender = new TestCurlSender();
    $sender->fakeResponse = '{"message":"success"}';

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

it('still throws ConnectionError when curl_exec returns false', function () {
    $sender = new TestCurlSender();
    $sender->fakeResponse = false;

    $sender->post(
        'https://example.com/api',
        'fake-api-key',
        ['test' => 'payload'],
        FlarePayloadType::Error,
        function (Response $r) {}
    );
})->throws(ConnectionError::class);

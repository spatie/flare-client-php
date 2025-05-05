<?php

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareHandlerStack;

test('middleware records successful requests and responses', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectExternalHttp());

    $flare->tracer->startTrace();

    $mockHandler = new MockHandler([
        new Response(
            200,
            ['Content-Type' => 'application/json', 'X-Test_Header' => 'another-value'],
            '{"success":true}'
        ),
    ]);

    $client = new Client(['handler' => new FlareHandlerStack($flare, $mockHandler)]);

    $response = $client->request('POST', 'https://example.com/api', [
        'headers' => [
            'Accept' => 'application/json',
            'X-Test-Header' => 'test-value',
        ],
        'body' => '{"foo":"bar"}',
    ]);

    expect($response->getStatusCode())->toBe(200);

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span)
        ->name->toBe('Http Request - example.com');

    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::HttpRequest)
        ->toHaveKey('url.full', 'https://example.com/api')
        ->toHaveKey('http.request.method', 'POST')
        ->toHaveKey('server.address', 'example.com')
        ->toHaveKey('server.port', null)
        ->toHaveKey('url.scheme', 'https')
        ->toHaveKey('url.path', '/api')
        ->toHaveKey('url.query', null)
        ->toHaveKey('url.fragment', null)
        ->toHaveKey('http.request.body.size', 13)
        ->toHaveKey('http.request.headers', [
            'User-Agent' => 'GuzzleHttp/7',
            'Host' => 'example.com',
            'Accept' => 'application/json',
            'X-Test-Header' => 'test-value',
        ])
        ->toHaveKey('http.response.status_code', 200)
        ->toHaveKey('http.response.body.size', 16)
        ->toHaveKey('http.response.headers', [
            'Content-Type' => 'application/json',
            'X-Test_Header' => 'another-value',
        ]);
});

test('middleware records HTTP error responses', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectExternalHttp());

    $flare->tracer->startTrace();

    $mockHandler = new MockHandler([
        new Response(
            404,
            ['Content-Type' => 'application/json'],
            '{"error":"Not found"}'
        ),
    ]);

    $client = new Client([
        'handler' => new FlareHandlerStack($flare, $mockHandler),
        'http_errors' => false // Prevent Guzzle from throwing exceptions for HTTP errors
    ]);

    $response = $client->request('GET', 'https://example.com/not-found');

    expect($response->getStatusCode())->toBe(404);

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span)
        ->name->toBe('Http Request - example.com');

    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::HttpRequest)
        ->toHaveKey('url.full', 'https://example.com/not-found')
        ->toHaveKey('http.request.method', 'GET')
        ->toHaveKey('server.address', 'example.com')
        ->toHaveKey('server.port', null)
        ->toHaveKey('url.scheme', 'https')
        ->toHaveKey('url.path', '/not-found')
        ->toHaveKey('url.query', null)
        ->toHaveKey('url.fragment', null)
        ->toHaveKey('http.request.headers', [
            'User-Agent' => 'GuzzleHttp/7',
            'Host' => 'example.com',
        ])
        ->toHaveKey('http.response.status_code', 404)
        ->toHaveKey('http.response.body.size', 21)
        ->toHaveKey('http.response.headers', [
            'Content-Type' => 'application/json',
        ]);
});

test('middleware records connection errors', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectExternalHttp());

    $flare->tracer->startTrace();

    // Setup mock with connection error
    $request = new Request('GET', 'https://example.com');
    $exception = new RequestException('Connection timed out', $request);
    $mockHandler = new MockHandler([$exception]);

    $client = new Client(['handler' => new FlareHandlerStack($flare, $mockHandler)]);

    try {
        $client->request('GET', 'https://example.com');
        $this->fail('Expected exception was not thrown');
    } catch (RequestException $e) {
        expect($e->getMessage())->toBe('Connection timed out');
    }

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span)
        ->name->toBe('Http Request - example.com');

    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::HttpRequest)
        ->toHaveKey('error.type', 'Connection timed out');
});

test('middleware correctly formats headers', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectExternalHttp());

    $flare->tracer->startTrace();

    $mockHandler = new MockHandler([
        new Response(
            200,
            [
                'Content-Type' => ['application/json'],
                'Set-Cookie' => ['cookie1=value1', 'cookie2=value2'],
                'X-Multiple' => ['value1', 'value2', 'value3']
            ],
            '{"success":true}'
        )
    ]);

    $client = new Client(['handler' => new FlareHandlerStack($flare, $mockHandler)]);

    $response = $client->request('GET', 'https://example.com', [
        'headers' => [
            'Accept' => ['application/json', 'text/html'],
            'X-Test' => ['value1', 'value2']
        ]
    ]);

    expect($response->getStatusCode())->toBe(200);

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span)
        ->name->toBe('Http Request - example.com');

    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::HttpRequest)
        ->toHaveKey('url.full', 'https://example.com')
        ->toHaveKey('http.request.method', 'GET')
        ->toHaveKey('http.request.headers', [
            'User-Agent' => 'GuzzleHttp/7',
            'Host' => 'example.com',
            'Accept' => 'application/json, text/html',
            'X-Test' => 'value1, value2',
        ])
        ->toHaveKey('http.response.status_code', 200)
        ->toHaveKey('http.response.headers', [
            'Content-Type' => 'application/json',
            'Set-Cookie' => 'cookie1=value1, cookie2=value2',
            'X-Multiple' => 'value1, value2, value3'
        ]);
});


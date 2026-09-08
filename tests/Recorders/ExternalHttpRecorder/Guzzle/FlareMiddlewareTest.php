<?php

use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\Utils as PromiseUtils;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Utils;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareHandlerStack;
use Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle\FlareMiddleware;

test('middleware records successful requests and responses', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    $mockHandler = new MockHandler([
        new Response(
            200,
            ['Content-Type' => 'application/json', 'X-Test_Header' => 'another-value'],
            '{"success":true}'
        ),
    ]);

    $client = new Client(['handler' => FlareHandlerStack::create($flare, $mockHandler)]);

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
            'User-Agent' => Utils::defaultUserAgent(),
            'Host' => 'example.com',
            'Accept' => 'application/json',
            'X-Test-Header' => 'test-value',
            'Content-Length' => '13',
        ])
        ->toHaveKey('http.response.status_code', 200)
        ->toHaveKey('http.response.body.size', 16)
        ->toHaveKey('http.response.headers', [
            'Content-Type' => 'application/json',
            'X-Test_Header' => 'another-value',
        ]);
});

test('middleware records HTTP error responses', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    $mockHandler = new MockHandler([
        new Response(
            404,
            ['Content-Type' => 'application/json'],
            '{"error":"Not found"}'
        ),
    ]);

    $client = new Client([
        'handler' => FlareHandlerStack::create($flare, $mockHandler),
        'http_errors' => false, // Prevent Guzzle from throwing exceptions for HTTP errors
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
            'User-Agent' => Utils::defaultUserAgent(),
            'Host' => 'example.com',
        ])
        ->toHaveKey('http.response.status_code', 404)
        ->toHaveKey('http.response.body.size', 21)
        ->toHaveKey('http.response.headers', [
            'Content-Type' => 'application/json',
        ])
        ->toHaveKey('error.type', '404');

    expect($span->status?->code)->toBe(SpanStatusCode::Error);
});

test('middleware records connection errors', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    // Setup mock with connection error
    $request = new Request('GET', 'https://example.com');
    $exception = new RequestException('Connection timed out', $request);
    $mockHandler = new MockHandler([$exception]);

    $client = new Client(['handler' => FlareHandlerStack::create($flare, $mockHandler)]);

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
        ->toHaveKey('error.type', RequestException::class);

    expect($span->status?->code)->toBe(SpanStatusCode::Error);
});

test('middleware correctly formats headers', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    $mockHandler = new MockHandler([
        new Response(
            200,
            [
                'Content-Type' => ['application/json'],
                'Set-Cookie' => ['cookie1=value1', 'cookie2=value2'],
                'X-Multiple' => ['value1', 'value2', 'value3'],
            ],
            '{"success":true}'
        ),
    ]);

    $client = new Client(['handler' => FlareHandlerStack::create($flare, $mockHandler)]);

    $response = $client->request('GET', 'https://example.com', [
        'headers' => [
            'Accept' => ['application/json', 'text/html'],
            'X-Test' => ['value1', 'value2'],
        ],
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
            'User-Agent' => Utils::defaultUserAgent(),
            'Host' => 'example.com',
            'Accept' => 'application/json, text/html',
            'X-Test' => 'value1, value2',
        ])
        ->toHaveKey('http.response.status_code', 200)
        ->toHaveKey('http.response.headers', [
            'Content-Type' => 'application/json',
            'Set-Cookie' => 'cookie1=value1, cookie2=value2',
            'X-Multiple' => 'value1, value2, value3',
        ]);
});

/*
 * Which response ends up on which span is not guaranteed for concurrent
 * transfers, the recorder ends spans in the order they were started.
 */
test('middleware closes every span when requests run concurrently', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    $middleware = new FlareMiddleware($flare);

    $promises = [];

    $handler = $middleware(function (Request $request, array $options) use (&$promises) {
        return $promises[$request->getUri()->getHost()] = new Promise();
    });

    $handler(new Request('GET', 'https://slow.test/'), []);
    $handler(new Request('GET', 'https://fast.test/'), []);

    $promises['slow.test']->resolve(new Response(500, ['Content-Length' => '5'], 'boom!'));
    $promises['fast.test']->resolve(new Response(201, ['Content-Length' => '3'], 'abc'));

    PromiseUtils::queue()->run();

    $spans = array_values($flare->tracer->currentTrace());

    expect($spans)->toHaveCount(2);

    foreach ($spans as $span) {
        expect($span)->end->not()->toBeNull();
        expect($span->attributes)->toHaveKey('http.response.status_code');
    }
});

test('middleware ends the span when the handler throws instead of rejecting', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    $middleware = new FlareMiddleware($flare);

    $handler = $middleware(function (Request $request, array $options) {
        throw new RuntimeException('Something went wrong');
    });

    expect(fn () => $handler(new Request('GET', 'https://example.com'), []))
        ->toThrow(RuntimeException::class);

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span)->end->not()->toBeNull();
    expect($span->attributes)->toHaveKey('error.type', RuntimeException::class);
    expect($span->status?->code)->toBe(SpanStatusCode::Error);
});

test('middleware records a rejection carrying a response as received', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    $request = new Request('GET', 'https://example.com');
    $response = new Response(200, ['Transfer-Encoding' => 'chunked'], 'truncated');

    $mockHandler = new MockHandler([
        new BadResponseException('Body could not be read', $request, $response),
    ]);

    $client = new Client(['handler' => FlareHandlerStack::create($flare, $mockHandler)]);

    expect(fn () => $client->request('GET', 'https://example.com'))
        ->toThrow(BadResponseException::class);

    $span = array_values($flare->tracer->currentTrace())[0];

    expect($span->attributes)
        ->toHaveKey('http.response.status_code', 200)
        ->toHaveKey('http.response.body.size', null)
        ->toHaveKey('error.type', BadResponseException::class);

    expect($span->status?->code)->toBe(SpanStatusCode::Error);
});

test('middleware records every redirect hop as its own span', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces()->collectExternalHttp());

    $flare->tracer->startTrace();

    $mockHandler = new MockHandler([
        new Response(302, ['Location' => 'https://example.com/second']),
        new Response(301, ['Location' => 'https://example.com/third']),
        new Response(200, [], 'done'),
    ]);

    $client = new Client(['handler' => FlareHandlerStack::create($flare, $mockHandler)]);

    $client->request('GET', 'https://example.com/first');

    $spans = array_values($flare->tracer->currentTrace());

    expect($spans)->toHaveCount(3);

    foreach ($spans as $span) {
        expect($span)->end->not()->toBeNull();
        expect($span->status?->code)->toBeNull();
    }

    expect($spans[0]->attributes)->toHaveKey('http.response.status_code', 302);
    expect($spans[1]->attributes)->toHaveKey('http.response.status_code', 301);
    expect($spans[2]->attributes)->toHaveKey('http.response.status_code', 200);

    expect($spans[0]->attributes)->not()->toHaveKey('error.type');
});

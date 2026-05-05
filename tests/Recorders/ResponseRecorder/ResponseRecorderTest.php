<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;

it('records a response span with the given attributes', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->response()->recordStart(['http.response.status_code' => 200]);

    expect($span)->not->toBeNull();
    expect($span->name)->toBe('Response');
    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::Response)
        ->toHaveKey('http.response.status_code', 200);
});

it('returns null when recordStart is called twice in a row', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    expect($flare->response()->recordStart())->not->toBeNull();
    expect($flare->response()->recordStart())->toBeNull();
});

it('returns null when recordEnd is called without a matching start', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    expect($flare->response()->recordEnd())->toBeNull();
});

it('closes the span and merges additional attributes on recordEnd', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $flare->response()->recordStart(['http.response.status_code' => 200]);
    $span = $flare->response()->recordEnd(['http.response.body.size' => 42]);

    expect($span)->not->toBeNull();
    expect($span->end)->not->toBeNull();
    expect($span->attributes)
        ->toHaveKey('http.response.status_code', 200)
        ->toHaveKey('http.response.body.size', 42);
});

it('records a complete response in a single call via recordResponse', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->response()->recordResponse(
        attributes: ['http.response.status_code' => 201],
        start: 1_000_000_000,
        end: 1_500_000_000,
    );

    expect($span)->not->toBeNull();
    expect($span->start)->toBe(1_000_000_000);
    expect($span->end)->toBe(1_500_000_000);
});

it('lets a second response span be recorded after the previous one ended', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $flare->response()->recordStart();
    $flare->response()->recordEnd();

    expect($flare->response()->recordStart())->not->toBeNull();
});

<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;

it('records a controller span with the given attributes', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->controller()->recordStart(['code.function' => 'PostsController@index']);

    expect($span)->not->toBeNull();
    expect($span->name)->toBe('Controller');
    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::Controller)
        ->toHaveKey('code.function', 'PostsController@index');
});

it('returns null when recordStart is called twice in a row', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    expect($flare->controller()->recordStart())->not->toBeNull();
    expect($flare->controller()->recordStart())->toBeNull();
});

it('returns null when recordEnd is called without a matching start', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    expect($flare->controller()->recordEnd())->toBeNull();
});

it('closes the span and merges additional attributes on recordEnd', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $flare->controller()->recordStart(['code.function' => 'PostsController@index']);
    $span = $flare->controller()->recordEnd(['code.namespace' => 'App\\Http\\Controllers']);

    expect($span)->not->toBeNull();
    expect($span->end)->not->toBeNull();
    expect($span->attributes)
        ->toHaveKey('code.function', 'PostsController@index')
        ->toHaveKey('code.namespace', 'App\\Http\\Controllers');
});

it('lets a second controller span be recorded after the previous one ended', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $flare->controller()->recordStart();
    $flare->controller()->recordEnd();

    expect($flare->controller()->recordStart())->not->toBeNull();
});

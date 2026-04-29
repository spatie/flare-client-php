<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeMemory;
use Symfony\Component\HttpFoundation\Request;

it('can trace requests', function () {
    FakeMemory::setup()->nextMemoryUsage(5 * 1024 * 1024);

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectRequests()->alwaysSampleTraces());

    $flare->tracer->startTrace();

    $flare->request()->recordStart();

    $flare->request()->recordEnd();

    expect($flare->tracer->currentTrace())->toHaveCount(1);

    $trace = $flare->tracer->currentTrace();

    $span = reset($trace);

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->spanId->not()->toBeNull()
        ->traceId->toBe($flare->tracer->currentTraceId());

    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::Request)
        ->toHaveKey('flare.peak_memory_usage', 5 * 1024 * 1024);
});

it('does not unsample when url does not match ignored list', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(ignoredUrls: ['/api/health']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $request = Request::create('https://example.com/api/users', 'GET');

    $span = $flare->request()->recordStart($request);

    expect($span)->not()->toBeNull();
    expect($flare->tracer->isSampling())->toBeTrue();
});

it('unsamples for an exact url match', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(ignoredUrls: ['/api/health']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $request = Request::create('https://example.com/api/health', 'GET');

    $span = $flare->request()->recordStart($request);

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('unsamples for a glob url match', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectRequests(ignoredUrls: ['/horizon/*']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $request = Request::create('https://example.com/horizon/jobs/123', 'GET');

    $span = $flare->request()->recordStart($request);

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

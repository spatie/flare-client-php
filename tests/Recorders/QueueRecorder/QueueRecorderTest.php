<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Shared\FakeApi;

it('records a queueing span when the job is not ignored', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->queue()->recordStartFromQueuedJob('App\\Jobs\\Send', 'App\\Jobs\\Send');

    expect($span)->not()->toBeNull();
    expect($span->name)->toBe('Queueing - App\\Jobs\\Send');
    expect($span->attributes)->toHaveKey('flare.span_type', SpanType::QueueingJob);
    expect($flare->tracer->isSampling())->toBeTrue();
});

it('merges additional attributes into the started span', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->queue()->recordStartFromQueuedJob(
        'App\\Jobs\\Send',
        'App\\Jobs\\Send',
        ['custom.key' => 'custom-value'],
    );

    expect($span->attributes)->toHaveKey('custom.key', 'custom-value');
});

it('records the end of a queueing span and merges additional attributes', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();
    $flare->queue()->recordStartFromQueuedJob('App\\Jobs\\Send', 'App\\Jobs\\Send');
    $span = $flare->queue()->recordEnd(['custom.key' => 'value']);

    expect($span)->not()->toBeNull();
    expect($span->end)->not()->toBeNull();
    expect($span->attributes)->toHaveKey('custom.key', 'value');
});

it('ignores queueing jobs by name from ignored_classes config', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['ignore-me']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->queue()->recordStartFromQueuedJob('ignore-me');

    expect($span)->toBeNull();
    expect($flare->tracer->isSamplingPaused())->toBeTrue();
});

it('ignores queueing jobs by class from ignored_classes config', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['App\\Jobs\\IgnoreMe']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->queue()->recordStartFromQueuedJob('something', 'App\\Jobs\\IgnoreMe');

    expect($span)->toBeNull();
    expect($flare->tracer->isSamplingPaused())->toBeTrue();
});

it('ignores queueing jobs using a wildcard pattern', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['App\\Jobs\\Internal\\*']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->queue()->recordStartFromQueuedJob('App\\Jobs\\Internal\\Cleanup', 'App\\Jobs\\Internal\\Cleanup');

    expect($span)->toBeNull();
    expect($flare->tracer->isSamplingPaused())->toBeTrue();
});

it('pauses sampling for an ignored job and resumes after the job ends', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['App\\Jobs\\Ignored']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    expect($flare->tracer->isSampling())->toBeTrue();

    $span = $flare->queue()->recordStartFromQueuedJob('App\\Jobs\\Ignored', 'App\\Jobs\\Ignored');

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->isSamplingPaused())->toBeTrue();

    $flare->queue()->recordEnd();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->isSamplingPaused())->toBeFalse();

    $flare->tracer->endTrace();

    FakeApi::assertTracesSent(0);
});

it('drops nested spans created while sampling is paused for ignored queueing', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['App\\Jobs\\Ignored']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();
    $parent = $flare->tracer->startSpan('Parent');

    $flare->queue()->recordStartFromQueuedJob('App\\Jobs\\Ignored', 'App\\Jobs\\Ignored');

    $flare->tracer->startSpan('Inside paused');

    $flare->queue()->recordEnd();

    $flare->tracer->endSpan($parent);
    $flare->tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectAllSpansClosed()
        ->expectSpan(0)
        ->expectName('Parent')
        ->expectMissingParentId();
});

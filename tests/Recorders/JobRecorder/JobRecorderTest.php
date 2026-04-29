<?php

use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\FlareMiddleware\AddJobInformation;
use Spatie\FlareClient\Support\Container;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeMemory;

beforeEach(function () {
    AddJobInformation::clearLatestJobInfo();
});

it('records a job span with the expected attributes', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->job()->recordStart('App\\Jobs\\Send', 'App\\Jobs\\Send');

    expect($span)->not()->toBeNull();
    expect($span->name)->toBe('Job - App\\Jobs\\Send');
    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::Job)
        ->toHaveKey('flare.entry_point.type', EntryPointType::Queue->value)
        ->toHaveKey('flare.entry_point.value', 'App\\Jobs\\Send')
        ->toHaveKey('flare.entry_point.handler.identifier', 'App\\Jobs\\Send')
        ->toHaveKey('flare.entry_point.handler.name', 'App\\Jobs\\Send')
        ->toHaveKey('flare.entry_point.handler.type', 'php_job');
});

it('falls back to the job name for the entry point value when no class is provided', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->job()->recordStart('legacy-handler');

    expect($span->attributes)
        ->toHaveKey('flare.entry_point.value', 'legacy-handler')
        ->toHaveKey('flare.entry_point.handler.identifier', 'legacy-handler')
        ->toHaveKey('flare.entry_point.handler.name', null);
});

it('merges additional attributes into the started span', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->job()->recordStart(
        'App\\Jobs\\Send',
        'App\\Jobs\\Send',
        attributes: ['custom.key' => 'custom-value'],
    );

    expect($span->attributes)->toHaveKey('custom.key', 'custom-value');
});

it('uses a custom entry point handler type when provided', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    $span = $flare->job()->recordStart(
        'App\\Jobs\\Send',
        'App\\Jobs\\Send',
        entryPointHandlerType: 'custom_handler',
    );

    expect($span->attributes)->toHaveKey('flare.entry_point.handler.type', 'custom_handler');
});


it('records the end of a job and includes peak memory usage', function () {
    FakeMemory::setup()->nextMemoryUsage(8 * 1024 * 1024);

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();
    $flare->job()->recordStart('App\\Jobs\\Send', 'App\\Jobs\\Send');
    $span = $flare->job()->recordEnd(['custom.key' => 'value']);

    expect($span)->not()->toBeNull();
    expect($span->end)->not()->toBeNull();
    expect($span->attributes)
        ->toHaveKey('flare.peak_memory_usage', 8 * 1024 * 1024)
        ->toHaveKey('custom.key', 'value');
});

it('ignores jobs by name from ignored_classes config', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['ignore-me']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->job()->recordStart('ignore-me', null);

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('ignores jobs by class from ignored_classes config', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['App\\Jobs\\IgnoreMe']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->job()->recordStart('something', 'App\\Jobs\\IgnoreMe');

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('ignores jobs using a wildcard pattern', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->collectJobs(ignoredClasses: ['App\\Jobs\\Internal\\*']),
        alwaysSampleTraces: true,
    );

    $flare->tracer->startTrace();

    $span = $flare->job()->recordStart('App\\Jobs\\Internal\\Cleanup', 'App\\Jobs\\Internal\\Cleanup');

    expect($span)->toBeNull();
    expect($flare->tracer->isSampling())->toBeFalse();
});

it('clears stale AddJobInformation state when starting a new job', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    AddJobInformation::setUsedTrackingUuid('previous-uuid');

    $flare->tracer->startTrace();
    $flare->job()->recordStart('App\\Jobs\\Send', 'App\\Jobs\\Send');

    expect(AddJobInformation::$usedTrackingUuid)->toBeNull();
    expect(AddJobInformation::$latestJob)->toBeNull();
});

it('writes the failed span and tracking uuid onto AddJobInformation when a job fails', function () {
    FakeIds::setup()->nextUuid('fake-uuid');

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();
    $flare->job()->recordStart('App\\Jobs\\Send', 'App\\Jobs\\Send');
    $span = $flare->job()->recordFailed(new Exception('Failed'));

    expect(AddJobInformation::$usedTrackingUuid)->toBe('fake-uuid');
    expect(AddJobInformation::$latestJob)->toBe($span);

    expect($span->end)->not()->toBeNull();
    expect($span->status?->code)->toBe(SpanStatusCode::Error);
    expect($span->status?->message)->toBe('Failed');

    expect($span->events)->toHaveCount(1);
    expect($span->events[0]->name)->toBe('Exception - '.Exception::class);
    expect($span->events[0]->attributes)
        ->toHaveKey('exception.id', 'fake-uuid')
        ->toHaveKey('flare.span_event_type', SpanEventType::Exception)
        ->toHaveKey('exception.message', 'Failed')
        ->toHaveKey('exception.type', Exception::class);
});

it('merges additional attributes when recording a failed job', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectJobs(), alwaysSampleTraces: true);

    $flare->tracer->startTrace();
    $flare->job()->recordStart('App\\Jobs\\Send', 'App\\Jobs\\Send');
    $span = $flare->job()->recordFailed(new Exception('Failed'), ['custom.key' => 'value']);

    expect($span->attributes)->toHaveKey('custom.key', 'value');
});

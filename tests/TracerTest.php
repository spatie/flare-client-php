<?php

namespace Spatie\FlareClient\Tests;

use Exception;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Sampling\NeverSampler;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;

it('can start a trace', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace();

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->not->toBeNull();
});

it('cannot start a trace when a trace is already happening', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace();

    expect(fn () => $tracer->startTrace())->toThrow(Exception::class, 'Trace already started');
});

it('cannot start a trace when sampling is disabled', function () {
    $tracer = setupFlare()->tracer;

    $tracer->samplingType = SamplingType::Disabled;

    expect(fn () => $tracer->startTrace())->toThrow(Exception::class, 'Trace cannot be started when sampling is disabled, off or already started');
});

it('cannot start a trace when sampling is off', function () {
    $tracer = setupFlare()->tracer;

    $tracer->samplingType = SamplingType::Off;

    expect(fn () => $tracer->startTrace())->toThrow(Exception::class, 'Trace cannot be started when sampling is disabled, off or already started');
});

it('is possible to end a trace and send it to the API', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace();

    $tracer->startSpan('Some span')->end();

    $tracer->endTrace();

    expect($tracer->samplingType)->toEqual(SamplingType::Waiting);
    expect($tracer->currentTraceId())->toBeNull();
    expect($tracer->traces)->toHaveCount(0);

    FakeSender::instance()->assertRequestsSent(1);
});

it('can potentially start a trace', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces())->tracer;

    $tracer->potentialStartTrace([]);

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->not()->toBeNull();
});

it('will not start a trace when the sampler decides to', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->sampler(NeverSampler::class))->tracer;

    $tracer->potentialStartTrace([]);

    expect($tracer->samplingType)->toEqual(SamplingType::Off);
    expect($tracer->currentTraceId())->toBeNull();
});

it('can potentially resume a trace', function () {
    $tracer = setupFlare()->tracer;

    $tracer->potentialStartTrace([
        'traceparent' => '00-traceid-spanid-01',
    ]);

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->toEqual('traceid');
    expect($tracer->currentSpanId())->toEqual('spanid');
});

it('will not resume a trace when the traceparent is invalid', function () {
    $tracer = setupFlare()->tracer;

    $tracer->potentialStartTrace([
        'traceparent' => 'invalid',
    ]);

    expect($tracer->samplingType)->toEqual(SamplingType::Off);
    expect($tracer->currentTraceId())->toBeNull();
    expect($tracer->currentSpanId())->toBeNull();
});

it('will not resume a trace when the sampling flag is false', function () {
    $tracer = setupFlare()->tracer;

    $tracer->potentialStartTrace([
        'traceparent' => '00-traceid-spanid-00',
    ]);

    expect($tracer->samplingType)->toEqual(SamplingType::Off);
    expect($tracer->currentTraceId())->toBeNull();
    expect($tracer->currentSpanId())->toBeNull();
});

it('can check whether the tracer is sampling', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces())->tracer;

    $tracer->potentialStartTrace([]);

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->not()->toBeNull();
});

it('can get the current span', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace();

    $span = $tracer->startSpan('Some span');

    expect($tracer->currentSpan())->toBe($span);
});

it('can check if the tracer has a current span', function () {
    $tracer = setupFlare()->tracer;

    expect($tracer->hasCurrentSpan())->toBeFalse();

    $tracer->startTrace();

    $tracer->startSpan('Some span');

    expect($tracer->hasCurrentSpan())->toBeTrue();
});

it('can check if the tracer has a current span of a specific type', function () {
    $tracer = setupFlare()->tracer;

    expect($tracer->hasCurrentSpan(SpanType::Query))->toBeFalse();

    $tracer->startTrace();

    $tracer->startSpan('Some span', attributes: ['flare.span_type' => SpanType::Query]);

    expect($tracer->hasCurrentSpan(SpanType::Query))->toBeTrue();
    expect($tracer->hasCurrentSpan(SpanType::Request))->toBeFalse();
});

it('can start and end a span', function () {
    FakeTime::setup('2019-01-01 12:34:56');
    FakeIds::setup()
        ->nextTraceId('fake-trace-id')
        ->nextSpanId('fake-span-id')
        ->nextSpanId('fake-span-id-2');

    $tracer = setupFlare()->tracer;

    $tracer->startTrace();

    $span = $tracer->startSpan('Some span');

    expect($span->name)->toEqual('Some span');
    expect($span->traceId)->toEqual('fake-trace-id');
    expect($span->spanId)->toEqual('fake-span-id');
    expect($span->start)->toBe(1546346096000000000);
    expect($span->end)->toBeNull();

    FakeTime::setup('2019-01-01 12:35:56');

    $span2 = $tracer->startSpan('Some span 2');

    expect($span2->name)->toEqual('Some span 2');
    expect($span2->traceId)->toEqual('fake-trace-id');
    expect($span2->spanId)->toEqual('fake-span-id-2');
    expect($span2->parentSpanId)->toEqual('fake-span-id');
    expect($span2->start)->toBe(1546346156000000000);
    expect($span2->end)->toBeNull();

    FakeTime::setup('2019-01-01 12:36:56');

    $tracer->endCurrentSpan();

    expect($span2->end)->toBe(1546346216000000000);
    expect($tracer->currentSpanId())->toBe('fake-span-id');


    FakeTime::setup('2019-01-01 12:37:56');

    $tracer->endCurrentSpan();

    expect($span->end)->toBe(1546346276000000000);
    expect($tracer->currentSpanId())->toBeNull();
});

it('can trash a trace and all its spans', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace();

    $tracer->startSpan('Some span');

    $tracer->trashCurrentTrace();

    expect($tracer->currentTraceId())->toBeNull();
    expect($tracer->currentSpanId())->toBeNull();
    expect($tracer->samplingType)->toEqual(SamplingType::Waiting);
});

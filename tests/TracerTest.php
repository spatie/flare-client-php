<?php

namespace Spatie\FlareClient\Tests;

use Exception;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Sampling\NeverSampler;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeSender;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\LaravelFlare\Tests\Concerns\ConfigureFlare;
use Throwable;

it('can start a trace', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->not->toBeNull();
});

it('it will not start another trace when some trace is already sampling', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();
    $tracer->startTrace();

    expect($tracer->getTraces())->toHaveCount(1);
});

it('cannot start a trace when sampling is disabled', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->neverSampleTraces())->tracer;

    $tracer->startTrace();

    expect($tracer->samplingType)->toBe(SamplingType::Off);
});

it('cannot start a trace when sampling is off', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->trace = false)->tracer;

    $tracer->startTrace();

    expect($tracer->samplingType)->toBe(SamplingType::Off);
});

it('is possible to end a trace and send it to the API', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startSpan('Some span');

    $tracer->endSpan();

    $tracer->endTrace();

    expect($tracer->samplingType)->toEqual(SamplingType::Waiting);
    expect($tracer->currentTraceId())->toBeNull();
    expect($tracer->getTraces())->toHaveCount(0);

    FakeSender::instance()->assertRequestsSent(1);
});


it('will not start a trace when the sampler decides to', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->sampler(NeverSampler::class))->tracer;

    $tracer->startTrace();

    expect($tracer->samplingType)->toEqual(SamplingType::Off);
    expect($tracer->currentTraceId())->not()->toBeNull();
});

it('can potentially resume a trace', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace('00-traceid-spanid-01');

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->toEqual('traceid');
    expect($tracer->currentSpanId())->toEqual('spanid');
});

it('will not resume a trace when the traceparent is invalid', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace('invalid');

    expect($tracer->samplingType)->toEqual(SamplingType::Off);
    expect($tracer->currentTraceId())->not()->toBeNull();
    expect($tracer->currentSpanId())->not()->toBeNull();
});

it('will not sample a trace when the sampling flag is disabled', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace('00-traceid-spanid-00');

    expect($tracer->samplingType)->toEqual(SamplingType::Off);
    expect($tracer->currentTraceId())->toBe('traceid');
    expect($tracer->currentSpanId())->toBe('spanid');
});

it('can check whether the tracer is sampling', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->alwaysSampleTraces())->tracer;

    $tracer->startTrace();

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->not()->toBeNull();
});

it('can get the current span', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

    $span = $tracer->startSpan('Some span');

    expect($tracer->currentSpan())->toBe($span);
});

it('can check if the tracer has a current span', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    expect($tracer->hasCurrentSpan())->toBeFalse();

    $tracer->startTrace();

    $tracer->startSpan('Some span');

    expect($tracer->hasCurrentSpan())->toBeTrue();
});

it('can check if the tracer has a current span of a specific type', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

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

    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $span = $tracer->startSpan('Some span');

    expect($span->name)->toEqual('Some span');
    expect($span->traceId)->toEqual('fake-trace-id');
    expect($span->spanId)->toEqual('fake-span-id');
    expect($span->start)->toBe(1546346096000000000);
    expect($span->end)->toBeNull();

    FakeTime::setCurrentTime('2019-01-01 12:35:56');

    $span2 = $tracer->startSpan('Some span 2');

    expect($span2->name)->toEqual('Some span 2');
    expect($span2->traceId)->toEqual('fake-trace-id');
    expect($span2->spanId)->toEqual('fake-span-id-2');
    expect($span2->parentSpanId)->toEqual('fake-span-id');
    expect($span2->start)->toBe(1546346156000000000);
    expect($span2->end)->toBeNull();

    FakeTime::setup('2019-01-01 12:36:56');

    $tracer->endSpan();

    expect($span2->end)->toBe(1546346216000000000);
    expect($tracer->currentSpanId())->toBe('fake-span-id');

    FakeTime::setup('2019-01-01 12:37:56');

    $tracer->endSpan();

    expect($span->end)->toBe(1546346276000000000);
    expect($tracer->currentSpanId())->toBeNull();
});

it('can start a span resuming a propagated trace', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace($tracer->ids->traceParent('fake_trace_id', 'fake_span_id', sampling: true));

    expect($tracer->isSampling())->toBeTrue();

    $tracer->startSpan('Some span');
    $tracer->endSpan();

    $span = array_values($tracer->currentTrace())[0];

    expect($span->traceId)->toEqual('fake_trace_id');
    expect($span->parentSpanId)->toEqual('fake_span_id');
    expect($span->spanId)->not()->toEqual('fake_span_id');
    expect($span->spanId)->not()->toBeNull();
});

it('can trash a trace and all its spans', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startSpan('Some span');

    $tracer->trashCurrentTrace();

    expect($tracer->currentTraceId())->toBeNull();
    expect($tracer->currentSpanId())->toBeNull();
    expect($tracer->samplingType)->toEqual(SamplingType::Waiting);
});

it('can run a span using closure', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->span(
        name: 'Some span',
        callback: fn () => 'do something',
        attributes: [
            'key' => 'value',
        ], endAttributes: fn (string $result) => [
        'result' => $result,
    ]);

    expect($tracer->currentSpanId())->toBeNull();
    expect($tracer->currentTrace())->toHaveCount(1);

    $span = array_values($tracer->currentTrace())[0];

    expect($span->name)->toEqual('Some span');
    expect($span->traceId)->toEqual($tracer->currentTraceId());
    expect($span->parentSpanId)->toBeNull();
    expect($span->end)->toBeGreaterThan($span->start);
    expect($span->attributes)
        ->toHaveKey('key', 'value')
        ->toHaveKey('result', 'do something')
        ->toHaveCount(2);
});

it('will still end a span when the callback throws an exception', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    try {
        $tracer->span(
            name: 'Some span',
            callback: fn () => throw new Exception('Something went wrong')
        );
    } catch (Throwable) {
        // Ignore the exception
    }

    expect($tracer->currentSpanId())->toBeNull();
    expect($tracer->currentTrace())->toHaveCount(1);

    $span = array_values($tracer->currentTrace())[0];

    expect($span->end)->toBeGreaterThan($span->start);
    expect($span->status)->not()->toBeNull();
    expect($span->status->code)->toBe(SpanStatusCode::Error);
    expect($span->status->message)->toEqual('Something went wrong');
});

it('can nest spans when tracing through callbacks', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->span(
        name: 'Some span',
        callback: function () use ($tracer) {
            $tracer->span(
                name: 'Some nested span',
                callback: fn () => 'do something',
            );
        }
    );

    expect($tracer->currentTrace())->toHaveCount(2);

    $spans = array_values($tracer->currentTrace());

    expect($spans[0]->name)->toEqual('Some span');
    expect($spans[0]->parentSpanId)->toBeNull();
    expect($spans[1]->name)->toEqual('Some nested span');
    expect($spans[1]->parentSpanId)->toEqual($spans[0]->spanId);
});

it('can start a span event when a current span is active', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startSpan('Some span');

    $tracer->spanEvent('Some event', [
        'key' => 'value',
    ]);

    $span = array_values($tracer->currentTrace())[0];

    expect($span->events)->toHaveCount(1);

    expect($span->events[0]->name)->toEqual('Some event');
    expect($span->events[0]->attributes)
        ->toHaveKey('key', 'value')
        ->toHaveCount(1);
    expect($span->events[0]->timestamp)->not()->toBeNull();
});

it('cannot add a span event when no current span is active', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    expect($tracer->spanEvent('Some event'))->toBeNull();
});

it('can remove a span event', function (){
    $tracer = setupFlare(fn(FlareConfig $config) => $config
        ->alwaysSampleTraces()
        ->configureSpanEvents(fn(SpanEvent $spanEvent) => $spanEvent->name === 'Delete' ? null : $spanEvent)
    )->tracer;

    $tracer->startSpan('Some span');

    $tracer->spanEvent('Some event');
    $tracer->spanEvent('Delete');

    $tracer->endSpan();

    $span = array_values($tracer->currentTrace())[0];

    expect($span->events)->toHaveCount(1);
    expect($span->events[0]->name)->toEqual('Some event');
});

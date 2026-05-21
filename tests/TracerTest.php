<?php

namespace Spatie\FlareClient\Tests;

use Exception;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\AddSpanResult;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Sampling\NeverSampler;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeMemory;
use Spatie\FlareClient\Tests\Shared\FakeTime;
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

    $firstTraceId = $tracer->currentTraceId();

    $tracer->startTrace();

    expect($tracer->currentTraceId())->toBe($firstTraceId);
});

it('cannot start a sampled trace when sampling is disabled', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->neverSampleTraces())->tracer;

    $tracer->startTrace();

    expect($tracer->sampling)->toBeFalse();
});

it('cannot start a trace when the tracer is disabled', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->trace = false)->tracer;

    $tracer->startTrace();

    expect($tracer->sampling)->toBeFalse();
});

it('is possible to end a trace and send it to the API', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

    $traceId = $tracer->currentTraceId();

    $tracer->startSpan('Some span');
    $tracer->endSpan();
    $tracer->endTrace();

    expect($tracer->sampling)->toBeFalse();
    expect($tracer->currentTraceId())->not()->toBe($traceId);
    expect($tracer->currentTrace())->toHaveCount(0);

    FakeApi::assertSent(traces: 1);
});


it('will not start a trace when the sampler decides to', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->sampler(NeverSampler::class))->tracer;

    $tracer->startTrace();

    expect($tracer->sampling)->toEqual(false);
    expect($tracer->currentTraceId())->not()->toBeNull();
});

it('can resume a trace with explicit ids and lets the sampler decide', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->sampleRate(1))->tracer;

    $tracer->startTrace(traceId: 'traceid', spanId: 'spanid');

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->toEqual('traceid');
    expect($tracer->currentSpanId())->toEqual('spanid');
});

it('requires both traceId and spanId when one is provided', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->sampleRate(1))->tracer;

    expect(fn () => $tracer->startTrace(traceId: 'traceid'))->toThrow(Exception::class);
    expect(fn () => $tracer->startTrace(spanId: 'span_id'))->toThrow(Exception::class);
});

it('can potentially resume a trace with traceparent', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->sampleRate(1))->tracer;

    $tracer->startTrace(traceParent: '00-traceid-spanid-01');

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->currentTraceId())->toEqual('traceid');
    expect($tracer->currentSpanId())->toEqual('spanid');
});

it('will not resume a trace when the traceparent is invalid', function () {
    $tracer = setupFlare(fn (FlareConfig $config) => $config->sampleRate(0))->tracer;

    $tracer->startTrace(traceParent: 'invalid');

    expect($tracer->sampling)->toBeFalse();
    expect($tracer->currentTraceId())->not()->toBeNull();
    expect($tracer->currentSpanId())->not()->toBeNull();
});

it('will not sample a trace when the sampling flag is disabled', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace(traceParent: '00-traceid-spanid-00');

    expect($tracer->sampling)->toBeFalse();
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

    $tracer->startTrace();

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

    $tracer->endTrace();

    expect($tracer->currentSpanId())->not()->toBe('fake-trace-id');
});

it('can start a span resuming a propagated trace', function () {
    $tracer = setupFlare()->tracer;

    $tracer->startTrace(traceParent: $tracer->ids->traceParent('fake_trace_id', 'fake_span_id', sampling: true));

    expect($tracer->isSampling())->toBeTrue();

    $tracer->startSpan('Some span');
    $tracer->endSpan();

    $span = array_values($tracer->currentTrace())[0];

    expect($span->traceId)->toEqual('fake_trace_id');
    expect($span->parentSpanId)->toEqual('fake_span_id');
    expect($span->spanId)->not()->toEqual('fake_span_id');
    expect($span->spanId)->not()->toBeNull();
});

it('can unsample a trace and all its spans', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

    $span = $tracer->startSpan('Some span');

    $tracer->unsample();

    expect($tracer->currentTraceId())->toBe($span->traceId);
    expect($tracer->currentSpanId())->toBe($span->spanId);
    expect($tracer->sampling)->toBeFalse();
    expect($tracer->currentTrace())->toBeEmpty();
});

it('can run a span using closure', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

    $traceId = $tracer->currentTraceId();

    $tracer->span(
        name: 'Some span',
        callback: fn () => 'do something',
        attributes: [
            'key' => 'value',
        ],
        endAttributes: fn (string $result) => [
            'result' => $result,
        ]
    );

    $tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpan(0)
        ->expectName('Some span')
        ->expectTraceId($traceId)
        ->expectMissingParentId()
        ->expectAttributes([
            'key' => 'value',
            'result' => 'do something',
        ]);
});

it('will still end a span when the callback throws an exception', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

    try {
        $tracer->span(
            name: 'Some span',
            callback: fn () => throw new Exception('Something went wrong')
        );
    } catch (Throwable) {
        // Ignore the exception
    }

    expect($tracer->currentSpanId())->toBe('-');

    $span = array_values($tracer->currentTrace())[0];

    expect($span->end)->toBeGreaterThan($span->start);
    expect($span->status)->not()->toBeNull();
    expect($span->status->code)->toBe(SpanStatusCode::Error);
    expect($span->status->message)->toEqual('Something went wrong');
});

it('can nest spans when tracing through callbacks', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

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

    $tracer->startTrace();
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

    $tracer->startTrace();

    expect($tracer->spanEvent('Some event'))->toBeNull();
});

it('can remove a span event', function () {
    $tracer = setupFlare(
        fn (FlareConfig $config) => $config
            ->alwaysSampleTraces()
            ->configureSpanEvents(fn (SpanEvent $spanEvent) => $spanEvent->name === 'Delete' ? null : $spanEvent)
    )->tracer;

    $tracer->startTrace();

    $tracer->startSpan('Some span');

    $tracer->spanEvent('Some event');
    $tracer->spanEvent('Delete');

    $tracer->endSpan();

    $span = array_values($tracer->currentTrace())[0];

    expect($span->events)->toHaveCount(1);
    expect($span->events[0]->name)->toEqual('Some event');
});

it('will always have a trace and span id even when a trace start call has not happened yet', function () {
    $tracer = setupFlare()->tracer;

    expect($tracer->currentTraceId())->not()->toBeNull();
    expect($tracer->currentSpanId())->not()->toBeNull();
});

it('will use the initial trace and span id until a trace is started', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    expect($tracer->currentTraceId())->not()->toBeNull();
    expect($tracer->currentSpanId())->not()->toBeNull();

    $currentTraceId = $tracer->currentTraceId();
    $currentSpanId = $tracer->currentSpanId();

    $tracer->startTrace();
    $span = $tracer->startSpan('Some span');

    expect($span->traceId)->toBe($currentTraceId);
    expect($span->spanId)->toBe($currentSpanId);
});

it('can include the peak memory usage when ending a span', function () {
    FakeMemory::setup()->nextMemoryUsage(1024 * 1024 * 5); // 5 MB

    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();
    $tracer->startSpan('Some span');
    $tracer->endSpan(includeMemoryUsage: true);
    $tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpan(0)
        ->expectAttributes([
            'flare.peak_memory_usage' => 5 * 1024 * 1024,
        ]);
});

it('can temporarily pause a trace sampling', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();
    $tracer->startSpan('Parent span');
    $tracer->startSpan('Some span');
    $tracer->pauseSampling();
    $tracer->startSpan('Some other span - unsampled');
    $tracer->endSpan();
    $tracer->resumeSampling();
    $tracer->startSpan('Some other span - sampled');
    $tracer->endSpan();
    $tracer->endSpan();
    $tracer->endSpan();
    $tracer->endTrace();

    $trace = FakeApi::lastTrace()->expectSpanCount(3)->expectAllSpansClosed();

    $parentSpan = $trace->expectSpan(0)
        ->expectName('Parent span')
        ->expectMissingParentId();

    $nestedSpan = $trace->expectSpan(1)
        ->expectName('Some span')
        ->expectParentId($parentSpan);

    $sampledNestedSpan = $trace->expectSpan(2)
        ->expectName('Some other span - sampled')
        ->expectParentId($nestedSpan);
});

it('treats pauseSampling and resumeSampling as idempotent toggles on a single bool', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->isSamplingPaused())->toBeFalse();

    $tracer->pauseSampling();

    expect($tracer->isSampling())->toBeFalse();
    expect($tracer->isSamplingPaused())->toBeTrue();

    $tracer->pauseSampling();

    expect($tracer->isSampling())->toBeFalse();
    expect($tracer->isSamplingPaused())->toBeTrue();

    $tracer->resumeSampling();

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->isSamplingPaused())->toBeFalse();

    $tracer->resumeSampling();

    expect($tracer->isSampling())->toBeTrue();
    expect($tracer->isSamplingPaused())->toBeFalse();
});

it('clears paused state on unsample so a stray resume cannot revive an unsampled trace', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();
    $tracer->pauseSampling();

    expect($tracer->isSamplingPaused())->toBeTrue();

    $tracer->unsample();

    expect($tracer->isSampling())->toBeFalse();
    expect($tracer->isSamplingPaused())->toBeFalse();

    $tracer->resumeSampling();

    expect($tracer->isSampling())->toBeFalse();
    expect($tracer->isSamplingPaused())->toBeFalse();
});

it('does not enter pause tracking when sampling is already off', function () {
    $tracer = setupFlare(alwaysSampleTraces: false)->tracer;

    expect($tracer->isSampling())->toBeFalse();

    $tracer->pauseSampling();

    expect($tracer->isSamplingPaused())->toBeFalse();
    expect($tracer->isSampling())->toBeFalse();

    $tracer->resumeSampling();

    expect($tracer->isSamplingPaused())->toBeFalse();
    expect($tracer->isSampling())->toBeFalse();
});

it('returns AddSpanResult::LimitReached from addSpan when span limit is reached', function () {
    $tracer = setupFlare(
        fn (FlareConfig $config) => $config
        ->alwaysSampleTraces()
        ->traceLimits(maxSpans: 2)
    )->tracer;

    $tracer->startTrace();

    $span1 = $tracer->startSpan('Span 1');
    $span2 = $tracer->startSpan('Span 2');

    expect($span1)->toBeInstanceOf(Span::class);
    expect($span2)->toBeInstanceOf(Span::class);
    expect($tracer->currentTrace())->toHaveCount(2);

    $span3 = $tracer->startSpan('Span 3');

    expect($span3)->toBe(AddSpanResult::LimitReached);
    expect($tracer->currentTrace())->toHaveCount(2);
});

it('does not corrupt the span tree when the limit is reached', function () {
    $tracer = setupFlare(
        fn (FlareConfig $config) => $config
        ->alwaysSampleTraces()
        ->traceLimits(maxSpans: 2)
    )->tracer;

    $tracer->startTrace();

    $span1 = $tracer->startSpan('Parent');
    $span2 = $tracer->startSpan('Child');
    $span3 = $tracer->startSpan('Over limit');

    expect($span3)->toBe(AddSpanResult::LimitReached);

    expect($tracer->currentSpan())->toBe($span2);

    $tracer->endSpan($span2);

    expect($tracer->currentSpan())->toBe($span1);

    $tracer->endSpan($span1);
    $tracer->endTrace();

    FakeApi::lastTrace()
        ->expectSpanCount(2)
        ->expectAllSpansClosed();
});

it('still executes the callback in span() when limit is reached', function () {
    $tracer = setupFlare(
        fn (FlareConfig $config) => $config
        ->alwaysSampleTraces()
        ->traceLimits(maxSpans: 1)
    )->tracer;

    $tracer->startTrace();
    $tracer->startSpan('Fill the limit');

    $executed = false;

    $result = $tracer->span('Over limit', function () use (&$executed) {
        $executed = true;

        return 'result';
    });

    expect($executed)->toBeTrue();
    expect($result)->toBe('result');
    expect($tracer->currentTrace())->toHaveCount(1);
});

it('keeps sampling when using a non-deferrable sampler and reevaluation is triggered', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->tracer->startTrace();

    expect($flare->tracer->isSampling())->toBeTrue();

    $flare->tracer->reevaluateSampling();

    expect($flare->tracer->isSampling())->toBeTrue();
});

it('drops an optimistically sampled trace when the route resolves to an ignored pattern', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->sampleTracesDynamic(
            baseRate: 1.0,
            rules: [SamplingRule::forRoute('/api/health', 0)],
        ),
        entryPoint: $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/api/health'),
    );

    $flare->tracer->startTrace();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->sampler->isDeferred())->toBeTrue();

    $flare->tracer->startSpan('Request Span');

    $entryPoint->setHandler('GET /api/health', 'HealthController', 'php_request');

    $flare->tracer->reevaluateSampling();

    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->sampler->isDeferred())->toBeFalse();
    expect($flare->tracer->currentTrace())->toHaveCount(0);
});

it('continues sampling when a deferred rule resolves to a matching rate', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->sampleTracesDynamic(
            baseRate: 0,
            rules: [SamplingRule::forRoute('/admin/*', 1.0)],
        ),
        entryPoint: $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users'),
    );

    $flare->tracer->startTrace();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->sampler->isDeferred())->toBeTrue();

    $flare->tracer->startSpan('Request Span');

    $entryPoint->setHandler('GET /admin/users', 'AdminController', 'php_request');

    $flare->tracer->reevaluateSampling();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->sampler->isDeferred())->toBeFalse();
    expect($flare->tracer->currentTrace())->toHaveCount(1);
});

it('clears deferred sampling state when the trace ends', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->sampleTracesDynamic(
            baseRate: 0,
            rules: [SamplingRule::forRoute('/admin/*', 1.0)],
        ),
        entryPoint: $entryPoint = new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users'),
    );

    $flare->tracer->startTrace();

    expect($flare->tracer->sampler->isDeferred())->toBeTrue();

    $entryPoint->setHandler('GET /admin/users', 'AdminController', 'php_request');
    $flare->tracer->reevaluateSampling();

    $flare->tracer->endTrace();

    expect($flare->tracer->sampler->isDeferred())->toBeFalse();
});

it('force-closes all open spans up the parent chain via gracefullyEndSpans', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    $tracer->startTrace();
    $parent = $tracer->startSpan('Parent');
    $child = $tracer->startSpan('Child');

    expect($parent->end)->toBeNull();
    expect($child->end)->toBeNull();

    $tracer->gracefullyEndSpans(force: true);

    expect($child->end)->not->toBeNull();
    expect($parent->end)->not->toBeNull();
});

it('respects the gracefulSpanEnderClosure when force is false', function () {
    $tracer = setupFlare(alwaysSampleTraces: true)->tracer;

    invade($tracer)->gracefulSpanEnderClosure = fn (Span $span) => $span->name !== 'Should stay open';

    $tracer->startTrace();
    $closable = $tracer->startSpan('Closable');
    $stayOpen = $tracer->startSpan('Should stay open');

    $tracer->gracefullyEndSpans();

    expect($stayOpen->end)->toBeNull();
});

it('clears deferred sampling state when unsampled', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->sampleTracesDynamic(
            baseRate: 0,
            rules: [SamplingRule::forRoute('/admin/*', 1.0)],
        ),
        entryPoint: new EntryPoint(EntryPointType::Web, 'https://example.com/admin/users'),
    );

    $flare->tracer->startTrace();

    expect($flare->tracer->sampler->isDeferred())->toBeTrue();

    $flare->tracer->unsample();

    expect($flare->tracer->sampler->isDeferred())->toBeFalse();
});

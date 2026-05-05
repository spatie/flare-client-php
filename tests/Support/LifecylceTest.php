<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\Shared\Samplers\FakeSampler;

it('will make a sampling decision at start', function () {
    // Six real start() calls fire across 11 iterations (the alternates trash);
    // three of the predetermined randoms fall under the 0.5 rate, so three sample.
    FakeSampler::setRandoms(0.1, 0.9, 0.1, 0.9, 0.1, 0.9);

    $flare = setupFlare(fn (FlareConfig $config) => $config->sampler(FakeSampler::class, ['rate' => 0.5]));

    foreach (range(0, 10) as $i) {
        $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);
        $flare->lifecycle->terminated(timeUnixNano: 10, additionalApplicationAttributes: ['stage_additional' => 'application']);
    }

    expect(count(FakeApi::$traces))->toBe(3);
});

it('will start an unsampled trace when the sampling decision is negative', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->neverSampleTraces());

    $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);

    expect($flare->tracer->sampling)->toBeFalse();
    expect($flare->tracer->currentTraceId())->not()->toBeNull();
    expect($flare->tracer->currentSpanId())->not()->toBeNull();
});

it('can continue a trace with a traceparent', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->neverSampleTraces());

    $traceParent = $flare->ids->traceParent(
        $traceId = $flare->ids->trace(),
        $spanId = $flare->ids->span(),
        sampling: true
    );

    $flare->lifecycle->start(traceparent: $traceParent);

    expect($flare->tracer->sampling)->toBeTrue();
    expect($flare->tracer->currentTraceId())->toBe($traceId);

    $flare->lifecycle->terminated();

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectTraceId($traceId)
        ->expectParentId($spanId);
});

it('can run through a lifecycle without subtasks, registration, booting or termination', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);
    $flare->lifecycle->terminated(timeUnixNano: 10, additionalApplicationAttributes: ['stage_additional' => 'application']);

    FakeApi::lastTrace()
        ->expectSpanCount(1)
        ->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(10)
        ->expectAttributes([
            'stage' => 'start',
            'stage_additional' => 'application',
        ]);
});

it('can run through a lifecycle without subtasks, registration or booting', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);
    $flare->lifecycle->terminating(timeUnixNano: 10, attributes: ['stage' => 'terminating']);
    $flare->lifecycle->terminated(timeUnixNano: 20, additionalTerminationAttributes: ['stage_additional' => 'terminating'], additionalApplicationAttributes: ['stage_additional' => 'application']);

    $trace = FakeApi::lastTrace()->expectSpanCount(2);

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(20)
        ->expectAttributes([
            'stage' => 'start',
            'stage_additional' => 'application',
        ]);

    $trace->expectSpan(1)
        ->expectType(SpanType::ApplicationTerminating)
        ->expectStart(10)
        ->expectEnd(20)
        ->expectAttributes([
            'stage' => 'terminating',
            'stage_additional' => 'terminating',
        ]);
});

it('can run through a lifecycle without subtasks, booting or termination', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);
    $flare->lifecycle->register(timeUnixNano: 10, attributes: ['stage' => 'register']);
    $flare->lifecycle->registered(timeUnixNano: 20, additionalAttributes: ['stage_additional' => 'register']);
    $flare->lifecycle->terminated(timeUnixNano: 30, additionalApplicationAttributes: ['stage_additional' => 'application']);

    $trace = FakeApi::lastTrace()->expectSpanCount(2);

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(30)
        ->expectAttributes([
            'stage' => 'start',
            'stage_additional' => 'application',
        ]);

    $trace->expectSpan(1)
        ->expectType(SpanType::ApplicationRegistration)
        ->expectStart(10)
        ->expectEnd(20)
        ->expectAttributes([
            'stage' => 'register',
            'stage_additional' => 'register',
        ]);
});

it('can run through a lifecycle without subtasks and booting', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);
    $flare->lifecycle->register(timeUnixNano: 10, attributes: ['stage' => 'register']);
    $flare->lifecycle->registered(timeUnixNano: 20, additionalAttributes: ['stage_additional' => 'register']);
    $flare->lifecycle->terminating(timeUnixNano: 30, attributes: ['stage' => 'termination']);
    $flare->lifecycle->terminated(timeUnixNano: 40, additionalTerminationAttributes: ['stage_additional' => 'termination'], additionalApplicationAttributes: ['stage_additional' => 'application']);

    $trace = FakeApi::lastTrace()->expectSpanCount(3);

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(40)
        ->expectAttributes([
            'stage' => 'start',
            'stage_additional' => 'application',
        ]);

    $trace->expectSpan(1)
        ->expectType(SpanType::ApplicationRegistration)
        ->expectStart(10)
        ->expectEnd(20)
        ->expectAttributes([
            'stage' => 'register',
            'stage_additional' => 'register',
        ]);

    $trace->expectSpan(2)
        ->expectType(SpanType::ApplicationTerminating)
        ->expectStart(30)
        ->expectEnd(40)
        ->expectAttributes([
            'stage' => 'termination',
            'stage_additional' => 'termination',
        ]);
});

it('can run through a lifecycle without subtasks, registration or termination', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);
    $flare->lifecycle->boot(timeUnixNano: 10, attributes: ['stage' => 'boot']);
    $flare->lifecycle->booted(timeUnixNano: 20, additionalAttributes: ['stage_additional' => 'boot']);
    $flare->lifecycle->terminated(timeUnixNano: 30, additionalApplicationAttributes: ['stage_additional' => 'application']);

    $trace = FakeApi::lastTrace()->expectSpanCount(2);

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(30)
        ->expectAttributes([
            'stage' => 'start',
            'stage_additional' => 'application',
        ]);

    $trace->expectSpan(1)
        ->expectType(SpanType::ApplicationBoot)
        ->expectStart(10)
        ->expectEnd(20)
        ->expectAttributes([
            'stage' => 'boot',
            'stage_additional' => 'boot',
        ]);
});


it('can run through a lifecycle without subtasks, registration', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0, attributes: ['stage' => 'start']);
    $flare->lifecycle->boot(timeUnixNano: 10, attributes: ['stage' => 'boot']);
    $flare->lifecycle->booted(timeUnixNano: 20, additionalAttributes: ['stage_additional' => 'boot']);
    $flare->lifecycle->terminating(timeUnixNano: 30, attributes: ['stage' => 'termination']);
    $flare->lifecycle->terminated(timeUnixNano: 40, additionalTerminationAttributes: ['stage_additional' => 'termination'], additionalApplicationAttributes: ['stage_additional' => 'application']);

    $trace = FakeApi::lastTrace()->expectSpanCount(3);

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(40)
        ->expectAttributes([
            'stage' => 'start',
            'stage_additional' => 'application',
        ]);

    $trace->expectSpan(1)
        ->expectType(SpanType::ApplicationBoot)
        ->expectStart(10)
        ->expectEnd(20)
        ->expectAttributes([
            'stage' => 'boot',
            'stage_additional' => 'boot',
        ]);

    $trace->expectSpan(2)
        ->expectType(SpanType::ApplicationTerminating)
        ->expectStart(30)
        ->expectEnd(40)
        ->expectAttributes([
            'stage' => 'termination',
            'stage_additional' => 'termination',
        ]);
});

it('can run through a lifecycle with subtasks', function () {
    $flare = setupFlare(alwaysSampleTraces: true, isUsingSubtasks: true);

    $flare->lifecycle->startSubtask();
    $flare->tracer->startSpan('Test Span', 0);
    $flare->tracer->endSpan(time: 10);
    $flare->lifecycle->endSubtask();

    $trace = FakeApi::lastTrace()->expectSpanCount(1);

    $trace->expectSpan(0)
        ->expectName('Test Span')
        ->expectStart(0)
        ->expectEnd(10);
});

it('will complete ignore all other lifecycle stages when using subtasks', function () {
    $flare = setupFlare(alwaysSampleTraces: true, isUsingSubtasks: true);

    $flare->lifecycle->start(timeUnixNano: 0);
    $flare->lifecycle->register(timeUnixNano: 10);
    $flare->lifecycle->registered(timeUnixNano: 20);
    $flare->lifecycle->boot(timeUnixNano: 30);
    $flare->lifecycle->booted(timeUnixNano: 40);
    $flare->lifecycle->startSubtask();
    $flare->tracer->startSpan('Test Span', 50);
    $flare->tracer->endSpan(time: 60);
    $flare->lifecycle->endSubtask();
    $flare->lifecycle->terminating(timeUnixNano: 70);
    $flare->lifecycle->terminated(timeUnixNano: 80);

    $trace = FakeApi::lastTrace()->expectSpanCount(1);

    $trace->expectSpan(0)
        ->expectName('Test Span')
        ->expectStart(50)
        ->expectEnd(60);
});

it('will in the end call a shutdown function which tries to close the current trace when still running', function () {
    FakeTime::setup(20);

    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0);
    $flare->lifecycle->terminating(timeUnixNano: 10);

    invade($flare->lifecycle)->shutdown();

    $trace = FakeApi::lastTrace()->expectSpanCount(2);

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(20);

    $trace->expectSpan(1)
        ->expectType(SpanType::ApplicationTerminating)
        ->expectStart(10)
        ->expectEnd(20);
});

it('force-closes orphaned spans during shutdown and finishes the trace', function () {
    FakeTime::setup(20);

    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0);
    $span = $flare->tracer->startSpan('Test Span', 5);
    $flare->tracer->endSpan(time: 10);
    $flare->lifecycle->terminating(timeUnixNano: 10);

    // Simulate an orphaned (unclosed) span — for example a View span left open
    // because an exception aborted the recorder before its matching close call.
    $span->end = null;

    invade($flare->lifecycle)->shutdown();

    FakeApi::assertTracesSent(1);
    expect($span->end)->not->toBeNull();
});

it('completes the lifecycle correctly when span limit is reached mid-request', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
        ->alwaysSampleTraces()
        ->traceLimits(maxSpans: 3)
    );

    $flare->lifecycle->start(timeUnixNano: 0);
    $flare->lifecycle->register(timeUnixNano: 10);
    $flare->lifecycle->registered(timeUnixNano: 20);
    $flare->lifecycle->boot(timeUnixNano: 30);
    $flare->lifecycle->booted(timeUnixNano: 40);

    // Won't be recorded
    $flare->lifecycle->terminating(timeUnixNano: 50);
    $flare->lifecycle->terminated(timeUnixNano: 60, additionalApplicationAttributes: ['stage_additional' => 'application']);

    $trace = FakeApi::lastTrace()
        ->expectSpanCount(3)
        ->expectAllSpansClosed();

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(60)
        ->expectAttributes([
            'stage_additional' => 'application',
        ]);
});

it('completes the lifecycle correctly when span limit is reached before lifecycle spans', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config
        ->alwaysSampleTraces()
        ->traceLimits(maxSpans: 1)
    );

    $flare->lifecycle->start(timeUnixNano: 0);

    // Won't be recorded
    $flare->lifecycle->register(timeUnixNano: 10);
    $flare->lifecycle->registered(timeUnixNano: 20);
    $flare->lifecycle->boot(timeUnixNano: 30);
    $flare->lifecycle->booted(timeUnixNano: 40);
    $flare->lifecycle->terminating(timeUnixNano: 50);
    $flare->lifecycle->terminated(timeUnixNano: 60, additionalApplicationAttributes: ['done' => true]);

    $trace = FakeApi::lastTrace()->expectSpanCount(1)->expectAllSpansClosed();

    $trace->expectSpan(0)
        ->expectType(SpanType::Application)
        ->expectStart(0)
        ->expectEnd(60)
        ->expectAttributes([
            'done' => true,
        ]);
});

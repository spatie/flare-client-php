<?php

namespace Spatie\FlareClient\Tests\Recorders\ApplicationRecorder;

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeSender;

it('can run through a complete application cycle', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 5)
        ->nextSpanId('fake-app-id', 'fake-registering-id', 'fake-booting-id', 'fake-span-id', 'fake-terminating-id');

    $flare->application()->recordStart(time: 0);
    $flare->application()->recordRegistering(time: 10);
    $flare->application()->recordRegistered(time: 20);
    $flare->application()->recordBooting(time: 30);
    $flare->application()->recordBooted(time: 40);
    $flare->tracer->startSpan('Custom span', time: 50);
    $flare->tracer->endSpan(time: 60);
    $flare->application()->recordTerminating(time: 70);
    $flare->application()->recordTerminated(time: 80);
    $flare->application()->recordEnd(time: 90);

    FakeSender::instance()->assertRequestsSent(1);
    $payload = FakeSender::instance()->getLastPayload();

    expect($payload)->toHaveCount(5);

    expect($payload[0])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-app-id')
        ->start->toBe(0)
        ->end->toBe(90)
        ->attributes->toHaveKey('flare.span_type', SpanType::Application);

    expect($payload[1])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-registering-id')
        ->parentSpanId->toBe('fake-app-id')
        ->start->toBe(10)
        ->end->toBe(20)
        ->attributes->toHaveKey('flare.span_type', SpanType::ApplicationRegistration);

    expect($payload[2])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-booting-id')
        ->parentSpanId->toBe('fake-app-id')
        ->start->toBe(30)
        ->end->toBe(40)
        ->attributes->toHaveKey('flare.span_type', SpanType::ApplicationBoot);

    expect($payload[3])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-span-id')
        ->parentSpanId->toBe('fake-app-id')
        ->start->toBe(50)
        ->end->toBe(60);

    expect($payload[4])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-terminating-id')
        ->parentSpanId->toBe('fake-app-id')
        ->start->toBe(70)
        ->end->toBe(80)
        ->attributes->toHaveKey('flare.span_type', SpanType::ApplicationTerminating);
});

it('can only start a trace when an application span is started', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->application()->recordRegistration(start: 10, end: 20);

    expect($flare->tracer->currentTraceId())->toBeNull();
    FakeSender::instance()->assertRequestsSent(0);

    $flare->application()->recordBoot(start: 10, end: 20);

    expect($flare->tracer->currentTraceId())->toBeNull();
    FakeSender::instance()->assertRequestsSent(0);

    $flare->application()->recordTermination(start: 10, end: 20);

    expect($flare->tracer->currentTraceId())->toBeNull();
    FakeSender::instance()->assertRequestsSent(0);
});

it('will automatically close other fases of the application', function () {
    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 4)
        ->nextSpanId('fake-app-id', 'fake-registering-id', 'fake-booting-id', 'fake-terminating-id');

    $flare = setupFlare(alwaysSampleTraces: true);

    $flare->application()->recordStart(time: 0);
    $flare->application()->recordRegistering(time: 10);
    $flare->application()->recordBooting(time: 20);
    $flare->application()->recordTerminating(time: 30);
    $flare->application()->recordEnd(time: 40);

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload)->toHaveCount(4);

    expect($payload[0])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-app-id')
        ->start->toBe(0)
        ->end->toBe(40)
        ->attributes->toHaveKey('flare.span_type', SpanType::Application);

    expect($payload[1])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-registering-id')
        ->parentSpanId->toBe('fake-app-id')
        ->start->toBe(10)
        ->end->toBe(20)
        ->attributes->toHaveKey('flare.span_type', SpanType::ApplicationRegistration);

    expect($payload[2])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-booting-id')
        ->parentSpanId->toBe('fake-app-id')
        ->start->toBe(20)
        ->end->toBe(30)
        ->attributes->toHaveKey('flare.span_type', SpanType::ApplicationBoot);

    expect($payload[3])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-terminating-id')
        ->parentSpanId->toBe('fake-app-id')
        ->start->toBe(30)
        ->end->toBe(40)
        ->attributes->toHaveKey('flare.span_type', SpanType::ApplicationTerminating);
});

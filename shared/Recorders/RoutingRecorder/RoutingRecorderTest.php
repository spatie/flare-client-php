<?php

namespace Spatie\FlareClient\Tests\Shared\Recorders\RoutingRecorder;

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeIds;
use Spatie\FlareClient\Tests\Shared\FakeSender;

test('it can run through a routing lifecycle', function () {
    $flare = setupFlare(fn(FlareConfig $config) => $config->collectRequests(), alwaysSampleTraces: true);

    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 6)
        ->nextSpanId('fake-app-id', 'fake-global-before-id', 'fake-before-id', 'fake-routing-id', 'fake-after-id', 'fake-global-after-id');

    $flare->application()->recordStart(time: 0);
    $flare->routing()->recordGlobalBeforeMiddlewareStart(time: 10);
    $flare->routing()->recordGlobalBeforeMiddlewareEnd(time: 20);
    $flare->routing()->recordBeforeMiddlewareStart(time: 30);
    $flare->routing()->recordBeforeMiddlewareEnd(time: 40);
    $flare->routing()->recordRoutingStart(time: 50);
    $flare->routing()->recordRoutingEnd(time: 60);
    $flare->routing()->recordAfterMiddlewareStart(time: 70);
    $flare->routing()->recordAfterMiddlewareEnd(time: 80);
    $flare->routing()->recordGlobalAfterMiddlewareStart(time: 90);
    $flare->routing()->recordGlobalAfterMiddlewareEnd(time: 100);
    $flare->application()->recordEnd(time: 110);

    FakeSender::instance()->assertRequestsSent(1);
    $payload = FakeSender::instance()->getLastPayload();

    expect($payload)->toHaveCount(6);

    expect($payload[0])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-app-id')
        ->start->toBe(0)
        ->end->toBe(110)
        ->attributes->toHaveKey('flare.span_type', SpanType::Application);

    expect($payload[1])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-global-before-id')
        ->start->toBe(10)
        ->end->toBe(20)
        ->attributes->toHaveKey('flare.span_type', SpanType::GlobalBeforeMiddleware);

    expect($payload[2])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-before-id')
        ->start->toBe(30)
        ->end->toBe(40)
        ->attributes->toHaveKey('flare.span_type', SpanType::BeforeMiddleware);

    expect($payload[3])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-routing-id')
        ->start->toBe(50)
        ->end->toBe(60)
        ->attributes->toHaveKey('flare.span_type', SpanType::Routing);

    expect($payload[4])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-after-id')
        ->start->toBe(70)
        ->end->toBe(80)
        ->attributes->toHaveKey('flare.span_type', SpanType::AfterMiddleware);

    expect($payload[5])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-global-after-id')
        ->start->toBe(90)
        ->end->toBe(100)
        ->attributes->toHaveKey('flare.span_type', SpanType::GlobalAfterMiddleware);
});

test('it can run through a routing lifecycle without global middleware', function () {
    $flare = setupFlare(fn(FlareConfig $config) => $config->collectRequests(), alwaysSampleTraces: true);

    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 6)
        ->nextSpanId('fake-app-id',  'fake-before-id', 'fake-routing-id', 'fake-after-id');

    $flare->application()->recordStart(time: 0);
    $flare->routing()->recordBeforeMiddleware(start: 0, end: 10);
    $flare->routing()->recordRouting(start: 10, end: 20);
    $flare->routing()->recordAfterMiddleware(start: 20, end: 30);
    $flare->application()->recordEnd(time: 30);

    FakeSender::instance()->assertRequestsSent(1);
    $payload = FakeSender::instance()->getLastPayload();

    expect($payload)->toHaveCount(4);

    expect($payload[0])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-app-id')
        ->start->toBe(0)
        ->end->toBe(30)
        ->attributes->toHaveKey('flare.span_type', SpanType::Application);

    expect($payload[1])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-before-id')
        ->start->toBe(0)
        ->end->toBe(10)
        ->attributes->toHaveKey('flare.span_type', SpanType::BeforeMiddleware);

    expect($payload[2])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-routing-id')
        ->start->toBe(10)
        ->end->toBe(20)
        ->attributes->toHaveKey('flare.span_type', SpanType::Routing);

    expect($payload[3])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-after-id')
        ->start->toBe(20)
        ->end->toBe(30)
        ->attributes->toHaveKey('flare.span_type', SpanType::AfterMiddleware);
});

it('will automatically close other fases of the routing', function () {
    $flare = setupFlare(fn (FlareConfig $config) => $config->collectRequests(), alwaysSampleTraces: true);

    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 6)
        ->nextSpanId('fake-app-id', 'fake-global-before-id', 'fake-before-id', 'fake-routing-id', 'fake-after-id', 'fake-global-after-id');

    $flare->application()->recordStart(time: 0);
    $flare->routing()->recordGlobalBeforeMiddlewareStart(time: 10);
    $flare->routing()->recordBeforeMiddlewareStart(time: 20);
    $flare->routing()->recordRoutingStart(time: 30);
    $flare->routing()->recordAfterMiddlewareStart(time: 40);
    $flare->routing()->recordGlobalAfterMiddlewareStart(time: 50);
    $flare->routing()->recordGlobalBeforeMiddlewareEnd(time: 60);
    $flare->application()->recordEnd(time: 60);

    FakeSender::instance()->assertRequestsSent(1);

    $payload = FakeSender::instance()->getLastPayload();

    expect($payload)->toHaveCount(6);

    expect($payload[0])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-app-id')
        ->start->toBe(0)
        ->end->toBe(60)
        ->attributes->toHaveKey('flare.span_type', SpanType::Application);

    expect($payload[1])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-global-before-id')
        ->start->toBe(10)
        ->end->toBe(20)
        ->attributes->toHaveKey('flare.span_type', SpanType::GlobalBeforeMiddleware);

    expect($payload[2])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-before-id')
        ->start->toBe(20)
        ->end->toBe(30)
        ->attributes->toHaveKey('flare.span_type', SpanType::BeforeMiddleware);

    expect($payload[3])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-routing-id')
        ->start->toBe(30)
        ->end->toBe(40)
        ->attributes->toHaveKey('flare.span_type', SpanType::Routing);

    expect($payload[4])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-after-id')
        ->start->toBe(40)
        ->end->toBe(50)
        ->attributes->toHaveKey('flare.span_type', SpanType::AfterMiddleware);

    expect($payload[5])
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe('fake-trace-id')
        ->spanId->toBe('fake-global-after-id')
        ->start->toBe(50)
        ->end->toBe(60)
        ->attributes->toHaveKey('flare.span_type', SpanType::GlobalAfterMiddleware);
});

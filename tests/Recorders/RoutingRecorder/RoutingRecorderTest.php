<?php

namespace Spatie\FlareClient\Tests\Shared\Recorders\RoutingRecorder;

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Tests\Shared\FakeApi;
use Spatie\FlareClient\Tests\Shared\FakeIds;

test('it can run through a routing lifecycle', function () {
    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 6)
        ->nextSpanId('fake-app-id', 'fake-global-before-id',  'fake-routing-id', 'fake-before-id', 'fake-after-id', 'fake-global-after-id');

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectRequests(), alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0);

    $flare->routing()->recordGlobalBeforeMiddlewareStart(time: 10);
    $flare->routing()->recordGlobalBeforeMiddlewareEnd(time: 20);
    $flare->routing()->recordRoutingStart(time: 30);
    $flare->routing()->recordRoutingEnd(time: 40);
    $flare->routing()->recordBeforeMiddlewareStart(time: 50);
    $flare->routing()->recordBeforeMiddlewareEnd(time: 60);
    $flare->routing()->recordAfterMiddlewareStart(time: 70);
    $flare->routing()->recordAfterMiddlewareEnd(time: 80);
    $flare->routing()->recordGlobalAfterMiddlewareStart(time: 90);
    $flare->routing()->recordGlobalAfterMiddlewareEnd(time: 100);

    $flare->lifecycle->terminated(timeUnixNano: 110);

    $trace = FakeApi::lastTrace()->expectSpanCount(6);

    $trace->expectSpan(0)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-app-id')
        ->expectStart(0)
        ->expectEnd(110)
        ->expectType(SpanType::Application);

    $trace->expectSpan(1)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-global-before-id')
        ->expectStart(10)
        ->expectEnd(20)
        ->expectType(SpanType::GlobalBeforeMiddleware);

    $trace->expectSpan(2)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-routing-id')
        ->expectStart(30)
        ->expectEnd(40)
        ->expectType(SpanType::Routing);

    $trace->expectSpan(3)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-before-id')
        ->expectStart(50)
        ->expectEnd(60)
        ->expectType(SpanType::BeforeMiddleware);

    $trace->expectSpan(4)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-after-id')
        ->expectStart(70)
        ->expectEnd(80)
        ->expectType(SpanType::AfterMiddleware);

    $trace->expectSpan(5)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-global-after-id')
        ->expectStart(90)
        ->expectEnd(100)
        ->expectType(SpanType::GlobalAfterMiddleware);
});

test('it can run through a routing lifecycle without global middleware', function () {
    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 6)
        ->nextSpanId('fake-app-id',  'fake-routing-id', 'fake-before-id', 'fake-after-id');

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectRequests(), alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0);

    $flare->routing()->recordRouting(start: 0, end: 10);
    $flare->routing()->recordBeforeMiddleware(start: 10, end: 20);
    $flare->routing()->recordAfterMiddleware(start: 20, end: 30);

    $flare->lifecycle->terminated(timeUnixNano: 30);

    $trace = FakeApi::lastTrace()->expectSpanCount(4);

    $trace->expectSpan(0)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-app-id')
        ->expectStart(0)
        ->expectEnd(30)
        ->expectType(SpanType::Application);

    $trace->expectSpan(1)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-routing-id')
        ->expectStart(0)
        ->expectEnd(10)
        ->expectType(SpanType::Routing);

    $trace->expectSpan(2)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-before-id')
        ->expectStart(10)
        ->expectEnd(20)
        ->expectType(SpanType::BeforeMiddleware);

    $trace->expectSpan(3)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-after-id')
        ->expectStart(20)
        ->expectEnd(30)
        ->expectType(SpanType::AfterMiddleware);
});

it('will automatically close other fases of the routing', function () {
    FakeIds::setup()
        ->nextTraceIdTimes('fake-trace-id', 6)
        ->nextSpanId('fake-app-id', 'fake-global-before-id',  'fake-routing-id', 'fake-before-id', 'fake-after-id', 'fake-global-after-id');

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectRequests(), alwaysSampleTraces: true);

    $flare->lifecycle->start(timeUnixNano: 0);

    $flare->routing()->recordGlobalBeforeMiddlewareStart(time: 10);
    $flare->routing()->recordRoutingStart(time: 20);
    $flare->routing()->recordBeforeMiddlewareStart(time: 30);
    $flare->routing()->recordAfterMiddlewareStart(time: 40);
    $flare->routing()->recordGlobalAfterMiddlewareStart(time: 50);
    $flare->routing()->recordGlobalAfterMiddlewareEnd(time: 60);

    $flare->lifecycle->terminated(timeUnixNano: 60);

    $trace = FakeApi::lastTrace()->expectSpanCount(6);

    $trace->expectSpan(0)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-app-id')
        ->expectStart(0)
        ->expectEnd(60)
        ->expectType(SpanType::Application);

    $trace->expectSpan(1)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-global-before-id')
        ->expectStart(10)
        ->expectEnd(20)
        ->expectType(SpanType::GlobalBeforeMiddleware);

    $trace->expectSpan(2)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-routing-id')
        ->expectStart(20)
        ->expectEnd(30)
        ->expectType(SpanType::Routing);

    $trace->expectSpan(3)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-before-id')
        ->expectStart(30)
        ->expectEnd(40)
        ->expectType(SpanType::BeforeMiddleware);

    $trace->expectSpan(4)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-after-id')
        ->expectStart(40)
        ->expectEnd(50)
        ->expectType(SpanType::AfterMiddleware);

    $trace->expectSpan(5)
        ->expectTrace('fake-trace-id')
        ->expectId('fake-global-after-id')
        ->expectStart(50)
        ->expectEnd(60)
        ->expectType(SpanType::GlobalAfterMiddleware);
});

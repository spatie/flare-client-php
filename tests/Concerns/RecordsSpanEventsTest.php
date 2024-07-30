<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\TestClasses\SpanEventsRecorder;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('is initially empty', function () {
    $recorder = new SpanEventsRecorder(setupFlare()->tracer);

    expect($recorder->getSpanEvents())->toHaveCount(0);
});

it('stores span events for reporting', function () {
    $recorder = new SpanEventsRecorder(setupFlare()->tracer);

    $recorder->record('Hello World');

    $spanEvents = $recorder->getSpanEvents();

    expect($spanEvents)->toHaveCount(1);

    expect($spanEvents[0])
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Span Event - Hello World')
        ->timeUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('does not store more than the max defined number of reported span events and removes the first ones', function () {
    $recorder = new SpanEventsRecorder(setupFlare()->tracer, maxReportedSpanEvents: 35);

    foreach (range(1, 40) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($recorder->getSpanEvents())->toHaveCount(35);
    expect($recorder->getSpanEvents()[0])->name->toBe('Span Event - Hello 6');
    expect($recorder->getSpanEvents()[34])->name->toBe('Span Event - Hello 40');
});

it('can disable the limit of span events stored for reporting', function () {
    $recorder = new SpanEventsRecorder(setupFlare()->tracer, maxReportedSpanEvents: null);

    foreach (range(1, 250) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($recorder->getSpanEvents())->toHaveCount(250);
});


it('can completely disable reporting', function (){
    $recorder = new SpanEventsRecorder(setupFlare()->tracer, reportSpanEvents: false);

    $recorder->record('Hello World');

    $spanEvents = $recorder->getSpanEvents();

    expect($spanEvents)->toHaveCount(0);
});

it('can trace span events', function () {
    $recorder = new SpanEventsRecorder($tracer = setupFlare()->tracer, traceSpanEvents: true);

    $tracer->startTrace();
    $tracer->addSpan($span = Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(1);

    $spanEvent = $span->events[0];

    expect($spanEvent)
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Span Event - Hello World')
        ->timeUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('will not trace span events when no span is current', function (){
    $recorder = new SpanEventsRecorder($tracer = setupFlare()->tracer, traceSpanEvents: true);

    $tracer->startTrace();
    $tracer->addSpan($span = Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: false);

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(0);
});

it('will not trace span events when not tracing', function (){
    $recorder = new SpanEventsRecorder($tracer = setupFlare()->tracer, traceSpanEvents: true);

    $tracer->addSpan($span = Span::build('fake-trace-id', 'Parent Span'), makeCurrent: true);

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(0);
});

it('will not trace span events when the span events per span limit is reached', function (){
    $flare = setupFlare(function (FlareConfig $config){
        $config->trace(maxSpanEventsPerSpan: 35);
    });

    $recorder = new SpanEventsRecorder($tracer = $flare->tracer, traceSpanEvents: true);

    $tracer->startTrace();
    $tracer->addSpan($span = Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    foreach (range(1, 40) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($span->events)->toHaveCount(35);
});

it('is possible to disable the recorder for tracing', function (){
    $recorder = new SpanEventsRecorder($tracer = setupFlare()->tracer, traceSpanEvents: false);

    $tracer->startTrace();
    $tracer->addSpan($span = Span::build($tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(0);
});

<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\SpanEventsRecorder;
use Spatie\FlareClient\Time\TimeHelper;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('is initially empty', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => true,
        'with_traces' => true,
    ]);

    expect($recorder->getSpanEvents())->toHaveCount(0);
});

it('stores span events for reporting', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => true,
    ]);

    $recorder->record('Hello World');

    $spanEvents = $recorder->getSpanEvents();

    expect($spanEvents)->toHaveCount(1);

    expect($spanEvents[0])
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Span Event - Hello World')
        ->timestamp->toBe(1546346096000000000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('does not store more than the max defined number of reported span events and removes the first ones', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => true,
        'max_items_with_errors' => 35,
    ]);

    foreach (range(1, 40) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($recorder->getSpanEvents())->toHaveCount(35);
    expect($recorder->getSpanEvents()[0])->name->toBe('Span Event - Hello 6');
    expect($recorder->getSpanEvents()[34])->name->toBe('Span Event - Hello 40');
});

it('can disable the limit of span events stored for reporting', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => true,
        'max_items_with_errors' => null,
    ]);

    foreach (range(1, 250) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($recorder->getSpanEvents())->toHaveCount(250);
});


it('can completely disable reporting', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => false,
    ]);

    $recorder->record('Hello World');

    $spanEvents = $recorder->getSpanEvents();

    expect($spanEvents)->toHaveCount(0);
});

it('can trace span events', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan(
        'parent span'
    );

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(1);

    $spanEvent = $span->events[0];

    expect($spanEvent)
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Span Event - Hello World')
        ->timestamp->toBe(1546346096000000000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('will not trace span events when no span is current', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();

    $span = $flare->tracer->startSpan(
        'root span'
    );

    $flare->tracer->startSpan(
        'parent span'
    );

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(0);
});

it('will not trace span events when not tracing', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $span = $flare->tracer->startSpan(
        'parent span'
    );

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(0);
});

it('will not trace span events when the span events per span limit is reached', function () {
    $flare = setupFlare(function (FlareConfig $config) {
        $config->trace(maxSpanEventsPerSpan: 35);
    });

    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan(
        'parent span'
    );

    foreach (range(1, 40) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($span->events)->toHaveCount(35);
});

it('is possible to disable the recorder for tracing', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => false,
    ]);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan(
        'parent span'
    );

    $recorder->record('Hello World');

    expect($span->events)->toHaveCount(0);
});

it('a closure passed span event will not be executed when not tracing or reporting', function () {
    class TestSpanEventRecorderExecution extends SpanEventsRecorder
    {
        public function record(string $message): ?SpanEvent
        {
            $this->persistEntry(fn () => throw new Exception('Closure executed'));
        }
    }

    $flare = setupFlare();

    expect(fn () => (new TestSpanEventRecorderExecution($flare->tracer, $flare->backTracer, [
        'with_traces' => true,
        'with_errors' => true,
    ]))->record('Hello World'))->toThrow(
        Exception::class,
        'Closure executed'
    );

    expect(fn () => (new TestSpanEventRecorderExecution($flare->tracer, $flare->backTracer, [
        'with_traces' => false,
        'with_errors' => false,
    ]))->record('Hello World'))->not()->toThrow(
        Exception::class,
        'Closure executed'
    );
});

it('can find origins when tracing events', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'find_origin' => true,
    ]);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('Parent Span');

    $recorder->record('Hello World');

    expect($span->events[0]->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('can will not find origins when tracing events when find origin is disabled', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'find_origin' => false,
    ]);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('Parent Span');

    $recorder->record('Hello World');

    expect($span->events[0]->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('is not possible to overwrite the find origin threshold', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'find_origin' => true,
        'find_origin_threshold' => TimeHelper::milliseconds(300),
    ]);

    $flare->tracer->startTrace();
    $span = $flare->tracer->startSpan('Parent Span');

    $recorder->record('Hello World');

    expect($span->events[0]->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not find origins when only reporting', function () {
    $flare = setupFlare();

    $recorder = new SpanEventsRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => true,
        'find_origin' => true,
    ]);

    $recorder->record('Hello World');

    expect($recorder->getSpanEvents()[0]->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

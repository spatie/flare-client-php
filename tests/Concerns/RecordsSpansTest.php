<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\TestClasses\SpanEventsRecorder;
use Spatie\FlareClient\Tests\TestClasses\SpansRecorder;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('is initially empty', function () {
    $recorder = new SpansRecorder(setupFlare()->tracer, [
        'report' => true,
        'trace' => true,
    ]);

    expect($recorder->getSpans())->toHaveCount(0);
});

it('stores spans for reporting', function () {
    $recorder = new SpansRecorder(setupFlare()->tracer, [
        'report' => true,
    ]);

    $recorder->record('Hello World');

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(1);

    expect($spans[0])
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Span - Hello World')
        ->startUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('does not store more than the max defined number of reported spans and removes the first ones', function () {
    $recorder = new SpansRecorder(setupFlare()->tracer, [
        'report' => true,
        'max_reported' => 35,
    ]);

    foreach (range(1, 40) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($recorder->getSpans())->toHaveCount(35);
    expect($recorder->getSpans()[0])->name->toBe('Span - Hello 6');
    expect($recorder->getSpans()[34])->name->toBe('Span - Hello 40');
});

it('can disable the limit of spans stored for reporting', function () {
    $recorder = new SpansRecorder(setupFlare()->tracer, [
        'report' => true,
        'max_reported' => null,
    ]);

    foreach (range(1, 250) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($recorder->getSpans())->toHaveCount(250);
});


it('can completely disable reporting', function () {
    $recorder = new SpansRecorder(setupFlare()->tracer, [
        'report' => false,
    ]);

    $recorder->record('Hello World');

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(0);
});

it('can trace spans', function () {
    $recorder = new SpansRecorder($tracer = setupFlare()->tracer, [
        'trace' => true,
    ]);

    $tracer->startTrace();

    $recorder->record('Hello World');

    $spans = $tracer->traces[$tracer->currentTraceId()];

    expect($spans)->toHaveCount(1);

    $span = reset($spans);

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Span - Hello World')
        ->startUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('will not trace span when not tracing', function () {
    $recorder = new SpansRecorder($tracer = setupFlare()->tracer, [
        'trace' => true,
    ]);

    $recorder->record('Hello World');

    expect($tracer->traces)->toHaveCount(0);
});

it('will not trace a span when the span limit is reached', function () {
    $flare = setupFlare(function (FlareConfig $config) {
        $config->trace(maxSpans: 35);
    });

    $recorder = new SpansRecorder($tracer = $flare->tracer,  [
        'trace' => true,
    ]);

    $tracer->startTrace();

    foreach (range(1, 40) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($tracer->traces[$tracer->currentTraceId()])->toHaveCount(35);
});

it('is possible to disable the recorder for tracing', function () {
    $recorder = new SpansRecorder($tracer = setupFlare()->tracer,  [
        'trace' => false,
    ]);

    $tracer->startTrace();

    $recorder->record('Hello World');

    expect($tracer->traces[$tracer->currentTraceId()] ?? [])->toHaveCount(0);
});

it('a closure passed span will not be executed when not tracing or reporting', function () {
    class TestSpanRecorderExecution extends SpansRecorder{
        public function record(string $message): ?Span
        {
            $this->persistEntry(fn () => throw new Exception('Closure executed'));
        }
    }

    expect(fn () => (new TestSpanRecorderExecution(setupFlare()->tracer, [
        'trace' => true,
        'report' => true,
    ]))->record('Hello World'))->toThrow(
        Exception::class,
        'Closure executed'
    );

    expect(fn () => (new TestSpanRecorderExecution(setupFlare()->tracer, [
        'trace' => false,
        'report' => false,
    ]))->record('Hello World'))->not()->toThrow(
        Exception::class,
        'Closure executed'
    );
});

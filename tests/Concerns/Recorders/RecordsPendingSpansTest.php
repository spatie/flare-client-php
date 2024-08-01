<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\TestClasses\PendingSpansRecorder;
use Spatie\FlareClient\Tests\TestClasses\SpansRecorder;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});


it('can start and end a span when reporting', function (){
    $recorder = new PendingSpansRecorder(setupFlare()->tracer, [
        'report' => true,
    ]);

    $recorder->pushSpan('Pending Span');
    $recorder->popSpan();

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(1);

    expect($spans[0])
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Pending Span')
        ->startUs->toBe(1546346096000)
        ->attributes->toHaveCount(0);
});

it('does not store more than the max defined number of reported spans and removes the first ones', function () {
    $recorder = new PendingSpansRecorder(setupFlare()->tracer, [
        'report' => true,
        'max_reported' => 35,
    ]);

    foreach (range(1, 40) as $i) {
        $recorder->pushSpan("Span - Hello {$i}");
        $recorder->popSpan();
    }

    expect($recorder->getSpans())->toHaveCount(35);
    expect($recorder->getSpans()[0])->name->toBe('Span - Hello 6');
    expect($recorder->getSpans()[34])->name->toBe('Span - Hello 40');
});

it('can disable the limit of spans stored for reporting', function () {
    $recorder = new PendingSpansRecorder(setupFlare()->tracer, [
        'report' => true,
        'max_reported' => null,
    ]);

    foreach (range(1, 250) as $i) {
        $recorder->pushSpan("Hello {$i}");
        $recorder->popSpan();
    }

    expect($recorder->getSpans())->toHaveCount(250);
});

it('can completely disable reporting', function () {
    $recorder = new PendingSpansRecorder(setupFlare()->tracer, [
        'report' => false,
    ]);

    $recorder->pushSpan('Pending Span');
    $recorder->popSpan();

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(0);
});

it('can trace spans', function () {
    $recorder = new PendingSpansRecorder($tracer = setupFlare()->tracer, [
        'trace' => true,
    ]);

    $tracer->startTrace();

    $pushedSpan = $recorder->pushSpan('Pending Span');

    expect($pushedSpan->endUs)->toBeNull();
    expect($tracer->currentSpanId())->toBe($pushedSpan->spanId);

    $recorder->popSpan();

    $spans = $tracer->traces[$tracer->currentTraceId()];

    expect($spans)->toHaveCount(1);

    $span = reset($spans);

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Pending Span')
        ->startUs->toBe(1546346096000)
        ->attributes->toHaveCount(0);
});

it('will not trace span when not tracing', function () {
    $recorder = new PendingSpansRecorder($tracer = setupFlare()->tracer, [
        'trace' => true,
    ]);

    $recorder->pushSpan('Hello World');
    $recorder->popSpan();

    expect($tracer->traces)->toHaveCount(0);
});

it('will not trace a span when the span limit is reached', function () {
    $flare = setupFlare(function (FlareConfig $config) {
        $config->trace(maxSpans: 35);
    });

    $recorder = new PendingSpansRecorder($tracer = $flare->tracer,  [
        'trace' => true,
    ]);

    $tracer->startTrace();

    foreach (range(1, 40) as $i) {
        $recorder->pushSpan("Hello {$i}");
        $recorder->popSpan();
    }

    expect($tracer->traces[$tracer->currentTraceId()])->toHaveCount(35);
});

it('is possible to disable the recorder for tracing', function () {
    $recorder = new PendingSpansRecorder($tracer = setupFlare()->tracer,  [
        'trace' => false,
    ]);

    $tracer->startTrace();

    $recorder->pushSpan('Hello World');
    $recorder->popSpan();

    expect($tracer->traces[$tracer->currentTraceId()] ?? [])->toHaveCount(0);
});

it('a closure passed span will not be executed when not tracing or reporting', function () {
    class TestPendingSpanRecorderExecution extends PendingSpansRecorder{
        public function record(string $message): ?Span
        {
            $this->persistEntry(fn () => throw new Exception('Closure executed'));
        }
    }

    expect(fn () => (new TestPendingSpanRecorderExecution(setupFlare()->tracer, [
        'trace' => true,
        'report' => true,
    ]))->record('Hello World'))->toThrow(
        Exception::class,
        'Closure executed'
    );

    expect(fn () => (new TestPendingSpanRecorderExecution(setupFlare()->tracer, [
        'trace' => false,
        'report' => false,
    ]))->record('Hello World'))->not()->toThrow(
        Exception::class,
        'Closure executed'
    );
});

it('will correctly nest spans', function (){
    $recorder = new PendingSpansRecorder($tracer = setupFlare()->tracer, [
        'trace' => true,
    ]);

    $tracer->startTrace();

    $spanA = $recorder->pushSpan('Pending Span A');

    expect($tracer->currentSpanId())->toBe($spanA->spanId);

    $spanB = $recorder->pushSpan('Pending Span B');

    expect($tracer->currentSpanId())->toBe($spanB->spanId);
    expect($spanB->parentSpanId)->toBe($spanA->spanId);

    $recorder->popSpan();

    expect($tracer->currentSpanId())->toBe($spanA->spanId);

    $recorder->popSpan();

    expect($tracer->currentSpanId())->toBeNull();
});

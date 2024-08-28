<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\SpansRecorder;
use Spatie\FlareClient\Time\TimeHelper;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('is initially empty', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'report' => true,
        'trace' => true,
    ]);

    expect($recorder->getSpans())->toHaveCount(0);
});

it('stores spans for reporting', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'report' => true,
    ]);

    $recorder->record('Hello World');

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(1);

    expect($spans[0])
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Span - Hello World')
        ->start->toBe(1546346096000000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('does not store more than the max defined number of reported spans and removes the first ones', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
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
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'report' => true,
        'max_reported' => null,
    ]);

    foreach (range(1, 250) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($recorder->getSpans())->toHaveCount(250);
});


it('can completely disable reporting', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'report' => false,
    ]);

    $recorder->record('Hello World');

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(0);
});

it('can trace spans', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'trace' => true,
    ]);

    $flare->tracer->startTrace();

    $recorder->record('Hello World');

    $spans = $flare->tracer->traces[$flare->tracer->currentTraceId()];

    expect($spans)->toHaveCount(1);

    $span = reset($spans);

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Span - Hello World')
        ->start->toBe(1546346096000000)
        ->attributes
        ->toHaveCount(1)
        ->toHaveKey('message', 'Hello World');
});

it('will not trace span when not tracing', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'trace' => true,
    ]);

    $recorder->record('Hello World');

    expect($flare->tracer->traces)->toHaveCount(0);
});

it('will not trace a span when the span limit is reached', function () {
    $flare = setupFlare(function (FlareConfig $config) {
        $config->trace(maxSpans: 35);
    });

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'trace' => true,
    ]);

    $flare->tracer->startTrace();

    foreach (range(1, 40) as $i) {
        $recorder->record("Hello {$i}");
    }

    expect($flare->tracer->traces[$flare->tracer->currentTraceId()])->toHaveCount(35);
});

it('is possible to disable the recorder for tracing', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'trace' => false,
    ]);

    $flare->tracer->startTrace();

    $recorder->record('Hello World');

    expect($flare->tracer->traces[$flare->tracer->currentTraceId()] ?? [])->toHaveCount(0);
});

it('a closure passed span will not be executed when not tracing or reporting', function () {
    class TestSpanRecorderExecution extends SpansRecorder
    {
        public function record(string $message, ?int $duration = null): ?Span
        {
            $this->persistEntry(fn () => throw new Exception('Closure executed'));
        }
    }

    $flare = setupFlare();

    expect(fn () => (new TestSpanRecorderExecution($flare->tracer, $flare->backTracer, [
        'trace' => true,
        'report' => true,
    ]))->record('Hello World'))->toThrow(
        Exception::class,
        'Closure executed'
    );

    expect(fn () => (new TestSpanRecorderExecution($flare->tracer, $flare->backTracer, [
        'trace' => false,
        'report' => false,
    ]))->record('Hello World'))->not()->toThrow(
        Exception::class,
        'Closure executed'
    );
});

it('can find origins when tracing events', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer, config:[
        'trace' => true,
        'find_origin' => true,
    ]);

    $flare->tracer->startTrace();

    $span = $recorder->record('Hello World');

    expect($span->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not find origins when tracing events when find origin is disabled', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer, config:[
        'trace' => true,
        'find_origin' => false,
    ]);

    $flare->tracer->startTrace();

    $span = $recorder->record('Hello World');

    expect($span->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('it will only find origins when the find origins threshold has been passed', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer, config:[
        'trace' => true,
        'find_origin' => true,
        'find_origin_threshold' => TimeHelper::milliseconds(300),
    ]);

    $flare->tracer->startTrace();

    $spanA = $recorder->record('Hello World', duration: TimeHelper::milliseconds(299));
    $spanB = $recorder->record('Hello World', duration: TimeHelper::milliseconds(300));
    $spanC = $recorder->record('Hello World', duration: TimeHelper::milliseconds(301));

    expect($spanA->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);

    expect($spanB->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);

    expect($spanC->attributes)->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

it('will not find origins when only reporting', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer, config:[
        'report' => true,
        'find_origin' => true,
    ]);

    $recorder->record('Hello World');

    expect($recorder->getSpans()[0]->attributes)->not()->toHaveKeys([
        'code.filepath',
        'code.lineno',
        'code.function',
    ]);
});

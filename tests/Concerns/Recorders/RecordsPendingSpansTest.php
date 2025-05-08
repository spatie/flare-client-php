<?php

use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\TestClasses\SpansRecorder;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});


it('can start and end a span when reporting', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_errors' => true,
    ]);

    $recorder->pushSpan('Pending Span');
    $recorder->popSpan();

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(1);

    expect($spans[0])
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Pending Span')
        ->start->toBe(1546346096000000000)
        ->attributes->toHaveCount(0);
});

it('does not store more than the max defined number of reported spans and removes the first ones', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_errors' => true,
        'max_items_with_errors' => 35,
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
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_errors' => true,
        'max_items_with_errors' => null,
    ]);

    foreach (range(1, 250) as $i) {
        $recorder->pushSpan("Hello {$i}");
        $recorder->popSpan();
    }

    expect($recorder->getSpans())->toHaveCount(250);
});

it('can completely disable reporting', function () {
    $flare = setupFlare();

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_errors' => false,
    ]);

    $recorder->pushSpan('Pending Span');
    $recorder->popSpan();

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(0);
});

it('can trace spans', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();

    $pushedSpan = $recorder->pushSpan('Pending Span');

    expect($pushedSpan->end)->toBeNull();
    expect($flare->tracer->currentSpanId())->toBe($pushedSpan->spanId);

    $recorder->popSpan();

    $spans = $flare->tracer->getTraces()[$flare->tracer->currentTraceId()];

    expect($spans)->toHaveCount(1);

    $span = reset($spans);

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Pending Span')
        ->start->toBe(1546346096000000000)
        ->attributes->toHaveCount(0);
});

it('will not trace span when not tracing', function () {
    $flare = setupFlare(fn(FlareConfig$config) => $config->neverSampleTraces());

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_traces' => true,
    ]);

    $recorder->pushSpan('Hello World');
    $recorder->popSpan();

    expect($flare->tracer->getTraces())->toHaveCount(0);
});

it('will not trace a span when the span limit is reached', function () {
    $flare = setupFlare(function (FlareConfig $config) {
        $config->alwaysSampleTraces()->trace(maxSpans: 35);
    });

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();

    foreach (range(1, 40) as $i) {
        $recorder->pushSpan("Hello {$i}");
        $recorder->popSpan();
    }

    expect($flare->tracer->getTraces()[$flare->tracer->currentTraceId()])->toHaveCount(35);
});

it('is possible to disable the recorder for tracing', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_traces' => false,
    ]);

    $flare->tracer->startTrace();

    $recorder->pushSpan('Hello World');
    $recorder->popSpan();

    expect($flare->tracer->getTraces()[$flare->tracer->currentTraceId()] ?? [])->toHaveCount(0);
});

it('a closure passed span will not be executed when not tracing or reporting', function () {
    class TestSpanRecorderExecution extends SpansRecorder
    {
        public function recordMessage(string $message): ?Span
        {
            $this->persistEntry(fn () => throw new Exception('Closure executed'));
        }
    }

    $flare = setupFlare(alwaysSampleTraces: true);

    expect(fn () => (new TestSpanRecorderExecution($flare->tracer, $flare->backTracer, config:[
        'with_traces' => true,
        'with_errors' => true,
    ]))->recordMessage('Hello World'))->toThrow(
        Exception::class,
        'Closure executed'
    );

    expect(fn () => (new TestSpanRecorderExecution($flare->tracer, $flare->backTracer, config:[
        'with_traces' => false,
        'with_errors' => false,
    ]))->recordMessage('Hello World'))->not()->toThrow(
        Exception::class,
        'Closure executed'
    );
});

it('will correctly nest spans', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();
    $parentSpan = $flare->tracer->startSpan('Parent');

    $spanA = $recorder->pushSpan('Pending Span A');

    expect($flare->tracer->currentSpanId())->toBe($spanA->spanId);
    expect($spanA->parentSpanId)->toBe($parentSpan->spanId);

    $spanB = $recorder->pushSpan('Pending Span B');

    expect($flare->tracer->currentSpanId())->toBe($spanB->spanId);
    expect($spanB->parentSpanId)->toBe($spanA->spanId);

    $recorder->popSpan();

    expect($flare->tracer->currentSpanId())->toBe($spanA->spanId);

    $recorder->popSpan();

    expect($flare->tracer->currentSpanId())->toBe($parentSpan->spanId);
});

it('can start and end traces when not present', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'can_start_traces' => true,
        'with_traces' => true,
    ]);

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorder->pushSpan('Pending Span');

    expect($flare->tracer->isSampling())->toBeTrue();

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
});

it('can start and end traces when not present decided by the span type', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorderA = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'can_start_traces' => true,
        'should_start_trace' => fn(Span $span) => $span->name === 'Trace staring span',
        'with_traces' => true,
    ]);

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorderA->pushSpan('Pending Span');

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorderA->pushSpan('Trace staring span');

    expect($flare->tracer->isSampling())->toBeTrue();

    $recorderA->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorderA->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
});

it('can start and end traces when not present (nested)', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'can_start_traces' => true,
        'with_traces' => true,
    ]);

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorder->pushSpan('Pending Span A');

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->pushSpan('Pending Span B');

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->getTraces())->toHaveCount(0); // trace was sent to API
});

it('will not start and end a trace when already sampling', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'can_start_traces' => true,
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();

    $recorder->pushSpan('Pending Span A');

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);
});

it('will not start a trace when tracing is completely disabled', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->trace(false)
    );

    $recorder = new SpansRecorder($flare->tracer, $flare->backTracer,  config:[
        'can_start_traces' => true,
        'with_traces' => true,
    ]);

    $recorder->pushSpan('Pending Span A');

    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->getTraces())->toHaveCount(1);
    expect($flare->tracer->currentTrace())->toBeEmpty();

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->getTraces())->toHaveCount(1);
    expect($flare->tracer->currentTrace())->toBeEmpty();
});

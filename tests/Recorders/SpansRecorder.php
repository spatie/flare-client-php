<?php

use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeTime;
use Spatie\FlareClient\Tests\Shared\Samplers\TestSampler;
use Spatie\FlareClient\Tests\TestClasses\ConcreteSpansRecorder;
use Spatie\FlareClient\Time\TimeHelper;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('will not report or trace a span when configured', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => false,
        'with_traces' => false,
    ]);

    // Simple record without trace

    $recorder->record('Span', 100);

    expect($recorder->getSpans())->toHaveCount(0);

    // Trace running

    $flare->tracer->startTrace();

    $recorder->record('Span', 100);

    expect($flare->tracer()->currentTrace())->toHaveCount(0);

    $flare->tracer()->endTrace();

    // Starting trace span

    $recorder->record('Span', 100, canStartTrace: true);

    expect($flare->tracer()->currentSpanId())->toBeNull();
});

it('can report a span with tracing disabled', function () {
    $flare = setupFlare();

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => true,
        'with_traces' => false,
    ]);

    $recorder->record('Span', 100);

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(1);

    expect($spans[0])
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Span')
        ->start->toBe(1546346096000000000 - 100)
        ->end->toBe(1546346096000000000)
        ->attributes->toHaveCount(0);
});


it('does not store more than the max defined number of reported spans and removes the first ones', function () {
    $flare = setupFlare();

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
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

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_errors' => true,
        'max_items_with_errors' => null,
    ]);

    foreach (range(1, 250) as $i) {
        $recorder->pushSpan("Hello {$i}");
        $recorder->popSpan();
    }

    expect($recorder->getSpans())->toHaveCount(250);
});

it('can trace spans', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
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
    $flare = setupFlare(fn (FlareConfig $config) => $config->neverSampleTraces());

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
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

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();

    foreach (range(1, 40) as $i) {
        $recorder->pushSpan("Hello {$i}");
        $recorder->popSpan();
    }

    expect($flare->tracer->getTraces()[$flare->tracer->currentTraceId()])->toHaveCount(35);
});


it('a closure passed span will not be executed when not tracing or reporting', function () {
    class TestConcreteSpanRecorderExecution extends ConcreteSpansRecorder
    {
        public function recordMessage(string $message): ?Span
        {
            $this->startSpan(fn () => throw new Exception('Closure executed'));
        }
    }

    $flare = setupFlare(alwaysSampleTraces: true);

    expect(fn () => (new TestConcreteSpanRecorderExecution($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'with_errors' => true,
    ]))->recordMessage('Hello World'))->toThrow(
        Exception::class,
        'Closure executed'
    );

    expect(fn () => (new TestConcreteSpanRecorderExecution($flare->tracer, $flare->backTracer, config: [
        'with_traces' => false,
        'with_errors' => false,
    ]))->recordMessage('Hello World'))->not()->toThrow(
        Exception::class,
        'Closure executed'
    );
});

it('will correctly nest spans', function () {
    $flare = setupFlare(alwaysSampleTraces: true);

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
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

it('can start and end sampled traces when required', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    expect($flare->tracer->isSampling())->toBeFalse();

    $span = $recorder->pushSpan('Pending Span', canStartTrace: true);

    expect($flare->tracer->isSampling())->toBeTrue();

    expect($span->traceId)->toBe($flare->tracer->currentTraceId());
    expect($span->spanId)->toBe($flare->tracer()->currentSpanId());
    expect($span->parentSpanId)->toBeNull();

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
});

it('can start sampled and end traces based upon a flag', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorderA = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorderA->pushSpan('Pending Span', canStartTrace: false);

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorderA->pushSpan('Trace staring span', canStartTrace: true);

    expect($flare->tracer->isSampling())->toBeTrue();

    $recorderA->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorderA->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
});

it('can start and end traces based upon a flag (nested)', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorder->pushSpan('Pending Span A', canStartTrace: true);

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->pushSpan('Pending Span B', canStartTrace: true);

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->getTraces())->toHaveCount(0); // trace was sent to API
});

it('will not start and end a trace sampled when already sampling', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->alwaysSampleTraces()
    );

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $flare->tracer->startTrace();

    $recorder->pushSpan('Pending Span A', canStartTrace: true);

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeTrue();
    expect($flare->tracer->getTraces())->toHaveCount(1);
});

it('will start and end a trace and when not sampled resets the tracer state at the end for the next run', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->neverSampleTraces()
    );

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $recorder->pushSpan('Pending Span A', canStartTrace: true);

    expect($flare->tracer->samplingType)->toBe(SamplingType::Off);
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->samplingType)->toBe(SamplingType::Waiting);
    expect($flare->tracer->getTraces())->toHaveCount(0);
});

it('can start and end unsampled traces based upon a flag (nested)', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->neverSampleTraces()
    );

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    expect($flare->tracer->isSampling())->toBeFalse();

    $recorder->pushSpan('Pending Span A', canStartTrace: true);

    expect($flare->tracer->samplingType)->toBe(SamplingType::Off);
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->pushSpan('Pending Span B', canStartTrace: true);

    expect($flare->tracer->samplingType)->toBe(SamplingType::Off);
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->samplingType)->toBe(SamplingType::Off);
    expect($flare->tracer->getTraces())->toHaveCount(1);

    $recorder->popSpan();

    expect($flare->tracer->samplingType)->toBe(SamplingType::Waiting);
    expect($flare->tracer->getTraces())->toHaveCount(0); // trace was sent to API
});

it('will respect the sampling decision by an earlier recorder when starting a trace', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->sampler(TestSampler::class)
    );

    $recorderA = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    $recorderB = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
    ]);

    TestSampler::neverSample();

    $recorderA->pushSpan('Pending Span A', canStartTrace: true);

    expect($flare->tracer->samplingType)->toBe(SamplingType::Off);

    TestSampler::alwaysSample(); // Change decision for next recorder

    $recorderB->pushSpan('Pending Span B', canStartTrace: true);

    expect($flare->tracer->samplingType)->toBe(SamplingType::Off); // Still off since already decided
});

it('will still update the span when required for reporting when staring and ending an unsampled trace ', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->neverSampleTraces()
    );

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'with_errors' => true,
    ]);

    $recorder->pushSpan('Pending Span A', canStartTrace: true);

    expect($flare->tracer->samplingType)->toBe(SamplingType::Off);
    expect($flare->tracer->getTraces())->toHaveCount(1);

    FakeTime::advance(seconds: 1);

    $recorder->popSpan();

    expect($flare->tracer->samplingType)->toBe(SamplingType::Waiting);
    expect($flare->tracer->getTraces())->toHaveCount(0);

    $spans = $recorder->getSpans();

    expect($spans)->toHaveCount(1);

    expect($spans[0])
        ->toBeInstanceOf(Span::class)
        ->name->toBe('Pending Span A')
        ->start->toBe(1546346096000000000)
        ->end->toBe(1546346096000000000 + TimeHelper::second())
        ->attributes->toHaveCount(0);
});


it('can resume a trace based upon a traceparent and will end it in the end', function () {
    $flare = setupFlare(
        fn (FlareConfig $config) => $config->neverSampleTraces()
    );

    // We will sample since it is a decision from above

    $recorder = new ConcreteSpansRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'with_errors' => true,
    ]);

    $ids = $flare->tracer->ids;

    $recorder->resumeTrace(
        $ids->traceParent(
            $traceId = $ids->trace(),
            $spanId = $ids->span(),
            true
        )
    );

    expect($flare->tracer->isSampling())->toBeTrue();

    $span = $recorder->record('Span', 100);

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->traceId->toBe($traceId)
        ->parentSpanId->toBe($spanId)
        ->spanId->not()->toBeNull();

    $recorder->popSpan();

    expect($flare->tracer->isSampling())->toBeFalse();
    expect($flare->tracer->getTraces())->toHaveCount(0); // Send to API
});

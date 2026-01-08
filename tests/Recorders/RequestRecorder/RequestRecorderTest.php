<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tests\Shared\FakeMemory;

it('can trace requests', function () {
    FakeMemory::setup()->nextMemoryUsage(5 * 1024 * 1024);

    $flare = setupFlare(fn (FlareConfig $config) => $config->collectRequests()->alwaysSampleTraces());

    $flare->tracer->startTrace();

    $flare->request()->recordStart();

    $flare->request()->recordEnd();

    expect($flare->tracer->currentTrace())->toHaveCount(1);

    $trace = $flare->tracer->currentTrace();

    $span = reset($trace);

    expect($span)
        ->toBeInstanceOf(Span::class)
        ->spanId->not()->toBeNull()
        ->traceId->toBe($flare->tracer->currentTraceId());

    expect($span->attributes)
        ->toHaveKey('flare.span_type', SpanType::Request)
        ->toHaveKey('flare.peak_memory_usage', 5 * 1024 * 1024);
});

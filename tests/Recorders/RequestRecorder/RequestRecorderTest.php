<?php

use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\QueryRecorder\QueryRecorder;
use Spatie\FlareClient\Recorders\QueryRecorder\QuerySpan;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Time\TimeHelper;

it('can trace requests', function () {
    $flare = setupFlare(fn(FlareConfig $config) => $config->collectRequests()->alwaysSampleTraces());

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
        ->toHaveKey('flare.span_type', SpanType::Request);
});

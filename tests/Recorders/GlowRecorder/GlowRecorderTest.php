<?php

use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\FlareConfig;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Spans\Span;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('stores glows for reporting and tracing', function () {
    $recorder = new GlowRecorder(setupFlare()->tracer, traceGlows: false, reportGlows: true, maxReportedGlows: 30);

    $glow = new GlowSpanEvent('Some name', 'info', [
        'some' => 'metadata',
    ]);

    $recorder->record(
        name: 'Some name',
        level: MessageLevels::INFO,
        context: ['some' => 'metadata'],
    );

    $glows = $recorder->getSpanEvents();

    expect($glows)->toHaveCount(1);

    expect($glow)
        ->toBeInstanceOf(GlowSpanEvent::class)
        ->name->toBe('Glow - Some name')
        ->timeUs->toBe(1546346096000);

    expect($glow->attributes)
        ->toHaveCount(4)
        ->toHaveKey('flare.span_event_type', SpanEventType::Glow)
        ->toHaveKey('glow.name', 'Some name')
        ->toHaveKey('glow.level', 'info')
        ->toHaveKey('glow.context', ['some' => 'metadata']);
});

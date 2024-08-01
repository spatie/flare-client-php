<?php

use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Recorders\LogRecorder\LogMessageSpanEvent;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('stores glows for reporting and tracing', function () {
    $recorder = new LogRecorder(setupFlare()->tracer, config: [
        'trace' => true,
        'report' => true,
        'max_reported' => 10,
    ]);

    $recorder->record(
        'Some name',
        MessageLevels::INFO,
        ['some' => 'metadata'],
    );

    $logs = $recorder->getSpanEvents();

    expect($logs)->toHaveCount(1);

    expect($logs[0])
        ->toBeInstanceOf(LogMessageSpanEvent::class)
        ->name->toBe('Log entry')
        ->timeUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(4)
        ->toHaveKey('flare.span_event_type', SpanEventType::Log)
        ->toHaveKey('log.message', 'Some name')
        ->toHaveKey('log.level', 'info')
        ->toHaveKey('log.context', ['some' => 'metadata']);
});


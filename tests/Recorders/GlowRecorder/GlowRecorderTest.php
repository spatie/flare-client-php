<?php

use Monolog\Level;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\Shared\FakeTime;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('stores glows for reporting and tracing', function () {
    $flare = setupFlare();

    $recorder = new GlowRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'with_errors' => true,
        'max_items_with_errors' => 10,
    ]);

    $recorder->record(
        name: 'Some name',
        level: Level::Info,
        context: ['some' => 'metadata'],
    );

    $glows = $recorder->getSpanEvents();

    expect($glows)->toHaveCount(1);

    $glow = $glows[0];

    expect($glow)
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Glow - Some name')
        ->timestamp->toBe(1546346096000000000);

    expect($glow->attributes)
        ->toHaveCount(4)
        ->toHaveKey('flare.span_event_type', SpanEventType::Glow)
        ->toHaveKey('glow.name', 'Some name')
        ->toHaveKey('glow.level', 'info')
        ->toHaveKey('glow.context', ['some' => 'metadata']);
});

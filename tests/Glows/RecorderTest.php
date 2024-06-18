<?php

use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Performance\Enums\SpanEventType;
use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowRecorder;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;

beforeEach(function () {
    $this->tracer = Flare::make('API-KEY')->tracer;

    useTime('2019-01-01 12:34:56');
});

it('is initially empty', function () {
    $recorder = new GlowRecorder($this->tracer);

    expect($recorder->getGlows())->toHaveCount(0);
});

it('stores glows', function () {
    $recorder = new GlowRecorder($this->tracer);

    $glow = new GlowSpanEvent('Some name', 'info', [
        'some' => 'metadata',
    ]);

    $recorder->record($glow);

    $glows = $recorder->getGlows();

    expect($glows)->toHaveCount(1);

    expect($glows[0])
        ->toBeArray()
        ->toHaveCount(5)
        ->toHaveKey('time', 1546346096)
        ->toHaveKey('name', 'Some name')
        ->toHaveKey('message_level', 'info')
        ->toHaveKey('meta_data', ['some' => 'metadata'])
        ->toHaveKey('microtime', 1546346096);
});

it('does not store more than the max defined number of glows', function () {
    $recorder = new GlowRecorder($this->tracer, maxGlows: 35);

    foreach (range(1, 40) as $i) {
        $recorder->record(new GlowSpanEvent('Glow '.$i));
    }

    expect($recorder->getGlows())->toHaveCount(35);
});

it('can trace glows', function () {
    $recorder = new GlowRecorder($this->tracer, traceGlows: true);

    $this->tracer->startTrace();
    $this->tracer->addSpan($span = Span::build($this->tracer->currentTraceId(), 'Parent Span'), makeCurrent: true);

    $glow = new GlowSpanEvent('Some name', 'info', [
        'some' => 'metadata',
    ]);

    $recorder->record($glow);

    expect($span->events)->toHaveCount(1);
    expect($span->events->current())
        ->toBeInstanceOf(GlowSpanEvent::class)
        ->name->toBe('Glow - Some name')
        ->timeUs->toBe(1546346096000);

    expect($span->events->current()->attributes)
        ->toHaveCount(4)
        ->toHaveKey('flare.span_event_type', SpanEventType::Glow)
        ->toHaveKey('glow.name', 'Some name')
        ->toHaveKey('glow.level', 'info')
        ->toHaveKey('glow.context', ['some' => 'metadata']);
});

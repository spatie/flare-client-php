<?php

use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\LogRecorder\LogRecorder;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tests\Shared\FakeTime;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('stores logs for reporting and tracing', function () {
    $flare = setupFlare();

    $recorder = new LogRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'with_errors' => true,
        'max_items_with_errors' => 10,
    ]);

    $recorder->record(
        'Some name',
        MessageLevels::Info,
        ['some' => 'metadata'],
    );

    $logs = $recorder->getSpanEvents();

    expect($logs)->toHaveCount(1);

    expect($logs[0])
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Log entry')
        ->timestamp->toBe(1546346096000000000)
        ->attributes
        ->toHaveCount(4)
        ->toHaveKey('flare.span_event_type', SpanEventType::Log)
        ->toHaveKey('log.message', 'Some name')
        ->toHaveKey('log.level', 'info')
        ->toHaveKey('log.context', ['some' => 'metadata']);
});

it('can only keep logs of a certain level', function () {
    $flare = setupFlare();

    $recorder = new LogRecorder($flare->tracer, $flare->backTracer, config: [
        'with_traces' => true,
        'with_errors' => true,
        'max_items_with_errors' => 10,
        'minimal_level' => MessageLevels::Critical,
    ]);

    foreach (MessageLevels::cases() as $level) {
        $recorder->record(
            'Some name',
            $level,
            ['some' => 'metadata'],
        );
    }

    $logs = $recorder->getSpanEvents();

    expect($logs)->toHaveCount(3);
    expect($logs[0]->attributes)->toHaveKey('log.level', 'emergency');
    expect($logs[1]->attributes)->toHaveKey('log.level', 'alert');
    expect($logs[2]->attributes)->toHaveKey('log.level', 'critical');
});

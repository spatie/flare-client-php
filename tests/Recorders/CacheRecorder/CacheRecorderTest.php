<?php

use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheSpanEvent;
use Spatie\FlareClient\Tests\Shared\FakeTime;

beforeEach(function () {
    FakeTime::setup('2019-01-01 12:34:56');
});

it('can record cache events', function () {
    $flare = setupFlare();

    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'trace' => true,
            'report' => true,
            'max_reported' => 10,
            'operations' => [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget],
        ]
    );

    $recorder->start();

    $recorder->recordHit('key', 'store');
    $recorder->recordMiss('key', 'store');
    $recorder->recordKeyWritten('key', 'store');
    $recorder->recordKeyForgotten('key', 'store');

    $events = $recorder->getSpanEvents();

    $this->assertCount(4, $events);

    expect($events[0])
        ->toBeInstanceOf(CacheSpanEvent::class)
        ->name->toBe('Cache hit - key')
        ->timestamp->toBe(1546346096000000000)
        ->attributes
        ->toHaveCount(5)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Get)
        ->toHaveKey('cache.result', CacheResult::Hit);

    expect($events[1])
        ->toBeInstanceOf(CacheSpanEvent::class)
        ->name->toBe('Cache miss - key')
        ->timestamp->toBe(1546346096000000000)
        ->attributes
        ->toHaveCount(5)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Get)
        ->toHaveKey('cache.result', CacheResult::Miss);

    expect($events[2])
        ->toBeInstanceOf(CacheSpanEvent::class)
        ->name->toBe('Cache key written - key')
        ->timestamp->toBe(1546346096000000000)
        ->attributes
        ->toHaveCount(5)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Set)
        ->toHaveKey('cache.result', CacheResult::Success);

    expect($events[3])
        ->toBeInstanceOf(CacheSpanEvent::class)
        ->name->toBe('Cache key forgotten - key')
        ->timestamp->toBe(1546346096000000000)
        ->attributes
        ->toHaveCount(5)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Forget)
        ->toHaveKey('cache.result', CacheResult::Success);
});

it('can limit the kinds of events being recorder', function () {
    $flare = setupFlare();
    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'trace' => true,
            'report' => true,
            'max_reported' => 10,
            'operations' => [CacheOperation::Set],
        ]
    );

    $recorder->start();

    $recorder->recordHit('key', 'store');
    $recorder->recordMiss('key', 'store');
    $keyWrite = $recorder->recordKeyWritten('key', 'store');
    $recorder->recordKeyForgotten('key', 'store');

    $events = $recorder->getSpanEvents();

    expect($events)->toBe([$keyWrite]);
});

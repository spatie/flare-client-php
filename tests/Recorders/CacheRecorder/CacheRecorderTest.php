<?php

use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheSpanEvent;
use Spatie\FlareClient\Recorders\DumpRecorder\DumpSpanEvent;

beforeEach(function () {
    useTime('2019-01-01 12:34:56');
});

it('can record cache events', function () {
    $recorder = new CacheRecorder(
        tracer: setupFlare()->tracer,
        config: [
            'trace' => true,
            'report' => true,
            'max_reported' => 10,
            'events' => [SpanEventType::CacheHit, SpanEventType::CacheMiss, SpanEventType::CacheKeyWritten, SpanEventType::CacheKeyForgotten],
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
        ->timeUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheHit)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store');

    expect($events[1])
        ->toBeInstanceOf(CacheSpanEvent::class)
        ->name->toBe('Cache miss - key')
        ->timeUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheMiss)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store');

    expect($events[2])
        ->toBeInstanceOf(CacheSpanEvent::class)
        ->name->toBe('Cache key written - key')
        ->timeUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheKeyWritten)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store');

    expect($events[3])
        ->toBeInstanceOf(CacheSpanEvent::class)
        ->name->toBe('Cache key forgotten - key')
        ->timeUs->toBe(1546346096000)
        ->attributes
        ->toHaveCount(3)
        ->toHaveKey('flare.span_event_type', SpanEventType::CacheKeyForgotten)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store');
});

it('can limit the kinds of events being recorder', function (){
    $recorder = new CacheRecorder(
        tracer: setupFlare()->tracer,
        config: [
            'trace' => true,
            'report' => true,
            'max_reported' => 10,
            'events' => [SpanEventType::CacheHit],
        ]
    );

    $recorder->start();

    $cacheHitEvent = $recorder->recordHit('key', 'store');
    $recorder->recordMiss('key', 'store');
    $recorder->recordKeyWritten('key', 'store');
    $recorder->recordKeyForgotten('key', 'store');

    $events = $recorder->getSpanEvents();

    expect($events)->toBe([$cacheHitEvent]);
});

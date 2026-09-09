<?php

use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\CacheRecorder\CacheRecorder;
use Spatie\FlareClient\Spans\SpanEvent;
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
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget],
        ]
    );

    $recorder->boot();

    $recorder->recordHit('key', 'store');
    $recorder->recordMiss('key', 'store');
    $recorder->recordKeyWritten('key', 'store');
    $recorder->recordKeyForgotten('key', 'store');

    $events = $recorder->getSpanEvents();

    $this->assertCount(4, $events);

    expect($events[0])
        ->toBeInstanceOf(SpanEvent::class)
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
        ->toBeInstanceOf(SpanEvent::class)
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
        ->toBeInstanceOf(SpanEvent::class)
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
        ->toBeInstanceOf(SpanEvent::class)
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
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [CacheOperation::Set],
        ]
    );

    $recorder->boot();

    $recorder->recordHit('key', 'store');
    $recorder->recordMiss('key', 'store');
    $keyWrite = $recorder->recordKeyWritten('key', 'store');
    $recorder->recordKeyForgotten('key', 'store');

    $events = $recorder->getSpanEvents();

    expect($events)->toBe([$keyWrite]);
});

it('can ignore cache keys', function () {
    $flare = setupFlare();

    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget],
            'ignored_keys' => ['/^framework:/', '/^exact-key$/'],
        ]
    );

    $recorder->boot();

    $recorder->recordHit('framework:schedule', 'store');
    $recorder->recordHit('exact-key', 'store');
    $recorded = $recorder->recordHit('some-key', 'store');

    expect($recorder->getSpanEvents())->toBe([$recorded]);
});

it('can record failed cache writes and forgets', function () {
    $flare = setupFlare();

    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget],
        ]
    );

    $recorder->boot();

    $recorder->recordKeyWriteFailed('key', 'store');
    $recorder->recordKeyForgetFailed('key', 'store');

    $events = $recorder->getSpanEvents();

    expect($events)->toHaveCount(2);

    expect($events[0])
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Cache key write failed - key')
        ->attributes
        ->toHaveCount(5)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Set)
        ->toHaveKey('cache.result', CacheResult::Failure);

    expect($events[1])
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Cache key forget failed - key')
        ->attributes
        ->toHaveCount(5)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.key', 'key')
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Forget)
        ->toHaveKey('cache.result', CacheResult::Failure);
});

it('ignores failed cache writes and forgets for ignored keys', function () {
    $flare = setupFlare();

    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget],
            'ignored_keys' => ['/^framework:/'],
        ]
    );

    $recorder->boot();

    $recorder->recordKeyWriteFailed('framework:schedule', 'store');
    $recorder->recordKeyForgetFailed('framework:schedule', 'store');

    expect($recorder->getSpanEvents())->toBeEmpty();
});

it('records a cache store failing over without filtering it', function () {
    $flare = setupFlare();

    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [],
            'ignored_keys' => ['//'],
        ]
    );

    $recorder->boot();

    $recorder->recordFailedOver('redis', new RuntimeException('Connection refused'));

    $events = $recorder->getSpanEvents();

    expect($events)->toHaveCount(1);

    expect($events[0])
        ->toBeInstanceOf(SpanEvent::class)
        ->name->toBe('Cache failed over - redis')
        ->attributes
        ->toHaveCount(5)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.store', 'redis')
        ->toHaveKey('cache.result', CacheResult::Failure)
        ->toHaveKey('exception.message', 'Connection refused')
        ->toHaveKey('exception.type', RuntimeException::class);
});

it('can record cache flushes without a key', function () {
    $flare = setupFlare();

    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [CacheOperation::Flush],
            'ignored_keys' => ['//'],
        ]
    );

    $recorder->boot();

    $recorder->recordFlushed('store');
    $recorder->recordFlushFailed('store');
    $recorder->recordLocksFlushed('store');
    $recorder->recordLocksFlushFailed('store');

    $events = $recorder->getSpanEvents();

    expect($events)->toHaveCount(4);

    expect(array_map(fn (SpanEvent $event) => $event->name, $events))->toBe([
        'Cache flushed',
        'Cache flush failed',
        'Cache locks flushed',
        'Cache locks flush failed',
    ]);

    expect($events[0])
        ->attributes
        ->toHaveCount(4)
        ->toHaveKey('flare.span_event_type', SpanEventType::Cache)
        ->toHaveKey('cache.store', 'store')
        ->toHaveKey('cache.operation', CacheOperation::Flush)
        ->toHaveKey('cache.result', CacheResult::Success)
        ->not()->toHaveKey('cache.key');

    expect($events[1]->attributes)->toHaveKey('cache.result', CacheResult::Failure);
    expect($events[2]->attributes)->toHaveKey('cache.result', CacheResult::Success);
    expect($events[3]->attributes)->toHaveKey('cache.result', CacheResult::Failure);
});

it('does not record cache flushes when the flush operation is not collected', function () {
    $flare = setupFlare();

    $recorder = new CacheRecorder(
        tracer: $flare->tracer,
        backTracer: $flare->backTracer,
        config: [
            'with_traces' => true,
            'with_errors' => true,
            'max_items_with_errors' => 10,
            'operations' => [CacheOperation::Get],
        ]
    );

    $recorder->boot();

    $recorder->recordFlushed('store');
    $recorder->recordLocksFlushed('store');

    expect($recorder->getSpanEvents())->toBeEmpty();
});

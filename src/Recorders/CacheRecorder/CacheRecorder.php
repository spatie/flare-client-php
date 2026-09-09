<?php

namespace Spatie\FlareClient\Recorders\CacheRecorder;

use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\PatternMatcher;
use Throwable;

class CacheRecorder extends SpanEventsRecorder
{
    /**
     * @var array<CacheOperation|string>
     */
    protected array $operations = [];

    /** @var array<int, string> */
    protected array $ignoredKeys = [];

    public const DEFAULT_OPERATIONS = [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget, CacheOperation::Flush];

    public static function type(): string|RecorderType
    {
        return RecorderType::Cache;
    }

    protected function configure(array $config): void
    {
        $this->operations = array_filter(array_map(
            fn (string|CacheOperation $spanEventType) => is_string($spanEventType) ? CacheOperation::tryFrom($spanEventType) : $spanEventType,
            $config['operations'] ?? [],
        ));

        $this->ignoredKeys = $config['ignored_keys'] ?? [];
    }

    public function recordHit(string $key, ?string $store): ?SpanEvent
    {
        return $this->record($key, $store, CacheOperation::Get, CacheResult::Hit);
    }

    public function recordMiss(string $key, ?string $store): ?SpanEvent
    {
        return $this->record($key, $store, CacheOperation::Get, CacheResult::Miss);
    }

    public function recordKeyWritten(string $key, ?string $store): ?SpanEvent
    {
        return $this->record($key, $store, CacheOperation::Set, CacheResult::Success);
    }

    public function recordKeyForgotten(string $key, ?string $store): ?SpanEvent
    {
        return $this->record($key, $store, CacheOperation::Forget, CacheResult::Success);
    }

    public function recordKeyWriteFailed(string $key, ?string $store): ?SpanEvent
    {
        return $this->record($key, $store, CacheOperation::Set, CacheResult::Failure);
    }

    public function recordKeyForgetFailed(string $key, ?string $store): ?SpanEvent
    {
        return $this->record($key, $store, CacheOperation::Forget, CacheResult::Failure);
    }

    public function recordFlushed(?string $store): ?SpanEvent
    {
        return $this->record('', $store, CacheOperation::Flush, CacheResult::Success);
    }

    public function recordFlushFailed(?string $store): ?SpanEvent
    {
        return $this->record('', $store, CacheOperation::Flush, CacheResult::Failure);
    }

    public function recordLocksFlushed(?string $store): ?SpanEvent
    {
        return $this->record('', $store, CacheOperation::Flush, CacheResult::Success, name: 'locks flushed');
    }

    public function recordLocksFlushFailed(?string $store): ?SpanEvent
    {
        return $this->record('', $store, CacheOperation::Flush, CacheResult::Failure, name: 'locks flush failed');
    }

    /**
     * A store falling over is an operational event without a key or an operation, so it is never
     * filtered by the ignored keys or the configured operations.
     */
    public function recordFailedOver(?string $store, ?Throwable $exception = null): ?SpanEvent
    {
        return $this->spanEvent(
            $store === null ? 'Cache failed over' : "Cache failed over - {$store}",
            attributes: [
                'flare.span_event_type' => SpanEventType::Cache,
                'cache.result' => CacheResult::Failure,
                'cache.store' => $store,
                ...$exception === null ? [] : [
                    'exception.message' => $exception->getMessage(),
                    'exception.type' => $exception::class,
                ],
            ]
        );
    }

    public function record(
        string $key,
        ?string $store,
        CacheOperation $operation,
        CacheResult $result,
        array $attributes = [],
        ?string $name = null,
    ): ?SpanEvent {
        if (! in_array($operation, $this->operations)) {
            return null;
        }

        if ($key !== '' && $this->shouldIgnoreKey($key)) {
            return null;
        }

        $name ??= match ([$operation, $result]) {
            [CacheOperation::Get, CacheResult::Hit] => 'hit',
            [CacheOperation::Get, CacheResult::Miss] => 'miss',
            [CacheOperation::Set, CacheResult::Success] => 'key written',
            [CacheOperation::Forget, CacheResult::Success] => 'key forgotten',
            [CacheOperation::Set, CacheResult::Failure] => 'key write failed',
            [CacheOperation::Forget, CacheResult::Failure] => 'key forget failed',
            [CacheOperation::Flush, CacheResult::Success] => 'flushed',
            [CacheOperation::Flush, CacheResult::Failure] => 'flush failed',
            default => '',
        };

        return $this->spanEvent(
            $key === '' ? "Cache {$name}" : "Cache {$name} - {$key}",
            attributes: [
                'flare.span_event_type' => SpanEventType::Cache,
                'cache.operation' => $operation,
                'cache.result' => $result,
                ...$key === '' ? [] : ['cache.key' => $key],
                'cache.store' => $store,
                ...$attributes,
            ]
        );
    }

    protected function shouldIgnoreKey(string $key): bool
    {
        return PatternMatcher::regexAny($key, [...$this->ignoredKeys, ...$this->defaultIgnoredKeys()]);
    }

    /** @return array<int, string> Regexes, just like the user configured ignored keys */
    protected function defaultIgnoredKeys(): array
    {
        return [];
    }
}

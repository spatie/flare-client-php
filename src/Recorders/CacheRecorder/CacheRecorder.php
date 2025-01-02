<?php

namespace Spatie\FlareClient\Recorders\CacheRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\RecorderType;

class CacheRecorder implements SpanEventsRecorder
{
    /** @use RecordsSpanEvents<CacheSpanEvent> */
    use RecordsSpanEvents;

    /**
     * @var array<CacheOperation|string>
     */
    protected array $operations = [];

    public static function type(): string|RecorderType
    {
        return RecorderType::Cache;
    }

    protected function configure(array $config): void
    {
        $this->configureRecorder($config);

        $this->operations = array_filter(array_map(
            fn (string|CacheOperation $spanEventType) => is_string($spanEventType) ? CacheOperation::tryFrom($spanEventType) : $spanEventType,
            $config['operations'] ?? [],
        ));
    }

    public function recordHit(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, CacheOperation::Get, CacheResult::Hit);
    }

    public function recordMiss(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, CacheOperation::Get, CacheResult::Miss);
    }

    public function recordKeyWritten(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, CacheOperation::Set, CacheResult::Success);
    }

    public function recordKeyForgotten(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, CacheOperation::Forget, CacheResult::Success);
    }

    public function record(
        string $key,
        ?string $store,
        CacheOperation $operation,
        CacheResult $result,
        ?array $attributes = null,
    ): ?CacheSpanEvent {
        if (! in_array($operation, $this->operations)) {
            return null;
        }

        return $this->persistEntry(
            fn () => (new CacheSpanEvent($key, $store, $operation, $result))->addAttributes($attributes),
        );
    }
}

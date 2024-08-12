<?php

namespace Spatie\FlareClient\Recorders\CacheRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;

/**
 * @uses RecordsSpanEvents<CacheSpanEvent>
 */
class CacheRecorder implements SpanEventsRecorder
{
    use RecordsSpanEvents;

    protected array $events = [];

    public static function type(): string|RecorderType
    {
        return RecorderType::Cache;
    }

    protected function configure(array $config): void
    {
        $this->configureRecorder($config);

        $this->events = array_filter(array_map(
            fn (string|FlareSpanEventType $spanEventType) => is_string($spanEventType) ? SpanEventType::tryFrom($spanEventType) : $spanEventType,
            $config['events'] ?? [],
        ));
    }

    public function recordHit(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, SpanEventType::CacheHit);
    }

    public function recordMiss(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, SpanEventType::CacheMiss);
    }

    public function recordKeyWritten(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, SpanEventType::CacheKeyWritten);
    }

    public function recordKeyForgotten(string $key, ?string $store): ?CacheSpanEvent
    {
        return $this->record($key, $store, SpanEventType::CacheKeyForgotten);
    }

    public function record(
        string $key,
        ?string $store,
        FlareSpanEventType $spanEventType,
        ?array $attributes = null,
    ): ?CacheSpanEvent {
        if (! in_array($spanEventType, $this->events)) {
            return null;
        }

        return $this->persistEntry(
            fn () => (new CacheSpanEvent($key, $store, $spanEventType, $attributes))->addAttributes($attributes),
        );
    }
}

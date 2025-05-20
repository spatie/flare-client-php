<?php

namespace Spatie\FlareClient\Recorders\CacheRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\SpanEvent;

class CacheRecorder extends Recorder implements SpanEventsRecorder
{
    /** @use RecordsSpanEvents<SpanEvent> */
    use RecordsSpanEvents;

    /**
     * @var array<CacheOperation|string>
     */
    protected array $operations = [];

    public const DEFAULT_OPERATIONS = [CacheOperation::Get, CacheOperation::Set, CacheOperation::Forget];

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

    public function record(
        string $key,
        ?string $store,
        CacheOperation $operation,
        CacheResult $result,
        array $attributes = [],
    ): ?SpanEvent {
        if (! in_array($operation, $this->operations)) {
            return null;
        }

        $name = match ([$operation, $result]) {
            [CacheOperation::Get, CacheResult::Hit] => 'hit',
            [CacheOperation::Get, CacheResult::Miss] => 'miss',
            [CacheOperation::Set, CacheResult::Success] => 'key written',
            [CacheOperation::Forget, CacheResult::Success] => 'key forgotten',
            default => '',
        };

        return $this->spanEvent(
            "Cache {$name} - {$key}",
            attributes: [
                'flare.span_event_type' => SpanEventType::Cache,
                'cache.operation' => $operation,
                'cache.result' => $result,
                'cache.key' => $key,
                'cache.store' => $store,
                ...$attributes,
            ]
        );
    }
}

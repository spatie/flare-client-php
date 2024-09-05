<?php

namespace Spatie\FlareClient\Recorders\CacheRecorder;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Enums\CacheOperation;
use Spatie\FlareClient\Enums\CacheResult;
use Spatie\FlareClient\Spans\SpanEvent;

class CacheSpanEvent extends SpanEvent
{
    public function __construct(
        public string $key,
        public ?string $store,
        public CacheOperation $operation,
        public CacheResult $result,
        public FlareSpanEventType $spanEventType,
        ?int $time = null,
    ) {
        $name = match ([$this->operation, $this->result]) {
            [CacheOperation::Get, CacheResult::Hit] => 'hit',
            [CacheOperation::Get, CacheResult::Miss] => 'miss',
            [CacheOperation::Set, CacheResult::Success] => 'key written',
            [CacheOperation::Forget, CacheResult::Success] => 'key forgotten',
            default => '',
        };

        parent::__construct(
            name: "Cache {$name} - {$key}",
            timestamp: $time ?? static::getCurrentTime(),
            attributes: $this->collectAttributes(),
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'cache.operation' => $this->operation,
            'cache.result' => $this->result,
            'cache.key' => $this->key,
            'cache.store' => $this->store,
        ];
    }
}

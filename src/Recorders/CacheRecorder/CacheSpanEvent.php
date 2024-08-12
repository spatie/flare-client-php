<?php

namespace Spatie\FlareClient\Recorders\CacheRecorder;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Spans\SpanEvent;

class CacheSpanEvent extends SpanEvent
{
    public function __construct(
        public string $key,
        public ?string $store,
        public FlareSpanEventType $spanEventType,
        ?int $time = null,
    ) {
        parent::__construct(
            name: ucfirst($spanEventType->humanReadable()) . " - {$key}",
            timestamp: $time ?? static::getCurrentTime(),
            attributes: $this->collectAttributes(),
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'cache.key' => $this->key,
            'cache.store' => $this->store,
        ];
    }
}

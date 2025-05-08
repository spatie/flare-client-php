<?php

namespace Spatie\FlareClient\Spans;

use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\SpanEventType;

class SpanEvent implements WithAttributes
{
    use HasAttributes;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $name,
        public int $timestamp,
        array $attributes,
    ) {
        $this->addAttributes($attributes);
    }

    /**
     * @return array{startTimeUnixNano: int, endTimeUnixNano: null, attributes: array, type: FlareSpanEventType}|null
     */
    public function toEvent(): ?array
    {
        $type = $this->attributes['flare.span_event_type'] ?? SpanEventType::Custom;

        unset($this->attributes['flare.span_event_type']);

        return [
            'startTimeUnixNano' => $this->timestamp,
            'endTimeUnixNano' => null,
            'attributes' => $this->attributes,
            'type' => $type,
        ];
    }
}

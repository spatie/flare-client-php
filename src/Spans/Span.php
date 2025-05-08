<?php

namespace Spatie\FlareClient\Spans;

use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\UsesIds;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;

class Span implements WithAttributes
{
    use HasAttributes;

    /** @var SpanEvent[] */
    public array $events = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId,
        public string $name,
        public int $start,
        public ?int $end,
        array $attributes = [],
        public int $droppedEventsCount = 0,
        public ?SpanStatus $status = null,
    ) {
        $this->addAttributes($attributes);
    }

    public function addEvent(SpanEvent ...$events): self
    {
        array_push($this->events, ...$events);

        return $this;
    }

    public function updateName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function setStatus(SpanStatusCode $code, ?string $message = null): self
    {
        $this->status = new SpanStatus($code, $message);

        return $this;
    }

    /**
     * @return array{startTimeUnixNano: int, endTimeUnixNano: int|null, attributes: array, type: FlareSpanType}|null
     */
    public function toEvent(): ?array
    {
        $type = $this->attributes['flare.span_type'] ?? SpanType::Custom;

        unset($this->attributes['flare.span_type']);

        return [
            'startTimeUnixNano' => $this->start,
            'endTimeUnixNano' => $this->end,
            'attributes' => $this->attributes,
            'type' => $type,
        ];
    }
}

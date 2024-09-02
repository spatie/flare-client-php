<?php

namespace Spatie\FlareClient\Spans;

use Spatie\FlareClient\Concerns\UsesIds;
use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Enums\SpanStatusCode;

class Span implements WithAttributes
{
    use HasAttributes;
    use UsesTime;
    use UsesIds;

    /** @var SpanEvent[] */
    public array $events = [];

    /**
     * @param array<string, mixed> $attributes
     */
    protected function __construct(
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
        $this->setAttributes($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, SpanEvent> $events
     */
    public static function build(
        string $traceId,
        string $name,
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
        ?string $id = null,
        ?string $parentId = null,
        array $attributes = [],
        array $events = [],
        SpanStatus $status = null,
    ): self {
        $id ??= self::ids()->span();

        [$start, $end] = match (true) {
            $start && $end => [$start, $end],
            $start && $duration => [$start, $start + $duration],
            $end && $duration => [$end - $duration, $end],
            $start && $end === null && $duration === null => [$start, null],
            $duration && $start === null && $end === null => [self::getCurrentTime() - $duration, self::getCurrentTime()],
            default => [self::getCurrentTime(), null],
        };

        $span = new self(
            traceId: $traceId,
            spanId: $id,
            parentSpanId: $parentId,
            name: $name,
            start: $start,
            end: $end,
            attributes: $attributes,
            droppedEventsCount: 0,
            status: $status,
        );

        $span->addEvent(...$events);

        return $span;
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

    public function end(?int $endUs = null): self
    {
        $this->end = $endUs ?? self::getCurrentTime();

        return $this;
    }

    public function setStatus(SpanStatusCode $code, ?string $message = null): self
    {
        $this->status = new SpanStatus($code, $message);

        return $this;
    }

    public function toTrace(): array
    {
        return [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'parentSpanId' => $this->parentSpanId,
            'name' => $this->name,
            'startTimeUnixNano' => $this->start * 1000,
            'endTimeUnixNano' => $this->end * 1000,
            'attributes' => $this->attributesAsArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
            'events' => array_map(fn (SpanEvent $event) => $event->toTrace(), $this->events),
            'droppedEventsCount' => $this->droppedEventsCount,
            'links' => [],
            'droppedLinksCount' => 0,
            'status' => $this->status?->toArray() ?? SpanStatus::default(),
        ];
    }

    public function toReport(): array
    {
        return [
            'name' => $this->name,
            'startTimeUnixNano' => $this->start * 1000,
            'endTimeUnixNano' => $this->end * 1000,
            'attributes' => $this->attributes,
        ];
    }
}

<?php

namespace Spatie\FlareClient\Performance\Spans;

use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Performance\Concerns\HasAttributes;
use Spatie\FlareClient\Performance\Support\SpanId;
use WeakMap;

class Span
{
    use HasAttributes;
    use UsesTime;

    /** @var WeakMap<SpanEvent, null> */
    public WeakMap $events;

    /** @var array<SpanEvent> */
    protected array $eventsStore = [];

    /**
     * @param array<string, mixed> $attributes
     */
    protected function __construct(
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId,
        public string $name,
        public int $startUs,
        public ?int $endUs,
        array $attributes = [],
        public int $droppedEventsCount = 0,
    ) {
        $this->events = new WeakMap();
        $this->setAttributes($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<int, SpanEvent> $events
     */
    public static function build(
        string $traceId,
        string $name,
        ?int $startUs = null,
        ?int $endUs = null,
        ?int $durationUs = null,
        ?string $id = null,
        ?string $parentId = null,
        array $attributes = [],
        array $events = [],
    ): self {
        $id ??= SpanId::generate();

        [$startUs, $endUs] = match (true) {
            $startUs && $endUs => [$startUs, $endUs],
            $startUs && $durationUs => [$startUs, $startUs + $durationUs],
            $endUs && $durationUs => [$endUs - $durationUs, $endUs],
            $startUs && $endUs === null && $durationUs === null => [$startUs, null],
            default => [self::getCurrentTime(), null],
        };

        $span = new self(
            traceId: $traceId,
            spanId: $id,
            parentSpanId: $parentId,
            name: $name,
            startUs: $startUs,
            endUs: $endUs,
            attributes: $attributes,
            droppedEventsCount: 0,
        );

        $span->addEvent(...$events);

        return $span;
    }

    public function addEvent(SpanEvent ...$events): self
    {
        foreach ($events as $event) {
            $this->eventsStore[] = $event;
        }

        $this->addRecordedEvent(...$events);

        return $this;
    }

    public function addRecordedEvent(SpanEvent ...$events): self
    {
        foreach ($events as $event) {
            $this->events[$event] = null;
        }

        return $this;
    }

    public function rename(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function end(int $endUs): self
    {
        $this->endUs = $endUs;

        return $this;
    }

    public function toArray(): array
    {
        $events = [];

        foreach ($this->events as $event => $nothing) {
            $events[] = $event->toArray();
        }

        return [
            'traceId' => $this->traceId,
            'spanId' => $this->spanId,
            'parentSpanId' => $this->parentSpanId,
            'name' => $this->name,
            'startTimeUnixNano' => $this->startUs * 1000,
            'endTimeUnixNano' => $this->endUs * 1000,
            'attributes' => $this->attributesToArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
            'events' => $events,
            'droppedEventsCount' => $this->droppedEventsCount,
        ];
    }
}

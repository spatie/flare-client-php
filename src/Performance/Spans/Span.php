<?php

namespace Spatie\FlareClient\Performance\Spans;

use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Performance\Concerns\HasAttributes;
use Spatie\FlareClient\Performance\Support\SpanId;

class Span
{
    use HasAttributes;
    use UsesTime;

    /**
     * @param array<string, mixed> $attributes
     * @param array<SpanEvent> $events
     */
    protected function __construct(
        public string $traceId,
        public string $spanId,
        public ?string $parentSpanId,
        public string $name,
        public int $startUs,
        public ?int $endUs,
        array $attributes = [],
        public array $events = [],
        public int $droppedEventsCount = 0,
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

        return new self(
            traceId: $traceId,
            id: $id,
            parentSpanId: $parentId,
            name: $name,
            startUs: $startUs,
            endUs: $endUs,
            attributes: $attributes,
            events: $events,
            droppedEventsCount: 0,
        );
    }

    public function addEvent(SpanEvent ...$events): self
    {
        array_push($this->events, ...$events);

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
        return [
            'traceId' => $this->traceId,
            'spanId' => $this->id,
            'parentSpanId' => $this->parentSpanId,
            'name' => $this->name,
            'startTimeUnixNano' => $this->startUs * 1000,
            'endTimeUnixNano' => $this->endUs * 1000,
            'attributes' => $this->attributesToArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
            'events' => array_map(fn (SpanEvent $event) => $event->toArray(), $this->events),
            'droppedEventsCount' => $this->droppedEventsCount,
        ];
    }
}

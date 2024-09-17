<?php

namespace Spatie\FlareClient\Spans;

use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\WithAttributes;

class SpanEvent implements WithAttributes
{
    use UsesTime;
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
     * @param array<string, mixed> $attributes
     */
    public static function build(
        string $name,
        ?int $timestamp = null,
        array $attributes = [],
    ): self {
        return new self(
            name: $name,
            timestamp: $timestamp ?? self::getCurrentTime(),
            attributes: $attributes,
        );
    }

    public function toTrace(): array
    {
        return [
            'name' => $this->name,
            'timeUnixNano' => $this->timestamp,
            'attributes' => $this->attributesAsArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
        ];
    }

    public function toEvent(): ?array
    {
        $type = $this->attributes['flare.span_event_type'] ?? null;

        if ($type === null) {
            return null;
        }

        unset($this->attributes['flare.span_event_type']);

        return [
            'startTimeUnixNano' => $this->timestamp,
            'endTimeUnixNano' => null,
            'attributes' => $this->attributes,
            'type' => $type,
        ];
    }
}

<?php

namespace Spatie\FlareClient\Performance\Spans;

use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Performance\Concerns\HasAttributes;

class SpanEvent
{
    use UsesTime;
    use HasAttributes;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $name,
        public int $timeUs,
        array $attributes,
    ) {
        $this->setAttributes($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function build(
        string $name,
        ?int $timeUs = null,
        array $attributes = [],
    ): self {
        return new self(
            name: $name,
            timeUs: $timeUs ?? self::getCurrentTime(),
            attributes: $attributes,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'timeUnixNano' => $this->timeUs * 1000,
            'attributes' => $this->attributesToArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
        ];
    }
}

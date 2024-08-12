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
        $this->setAttributes($attributes);
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
            'timeUnixNano' => $this->timestamp * 1000,
            'attributes' => $this->attributesAsArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
        ];
    }

    public function toReport(): array
    {
        return [
            'name' => $this->name,
            'timeUnixNano' => $this->timestamp * 1000,
            'attributes' => $this->attributes,
        ];
    }
}

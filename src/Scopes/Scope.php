<?php

namespace Spatie\FlareClient\Scopes;

use Spatie\FlareClient\Concerns\HasAttributes;
use Spatie\FlareClient\Contracts\WithAttributes;
use Spatie\FlareClient\Support\Telemetry;

class Scope implements WithAttributes
{
    use HasAttributes;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        public string $name,
        public string $version,
        array $attributes,
    ) {
        $this->setAttributes($attributes);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public static function build(
        array $attributes = [],
        string $name = Telemetry::NAME,
        string $version = Telemetry::VERSION,
    ): self {
        return new self(
            name: $name,
            version: $version,
            attributes: $attributes,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'attributes' => $this->attributesAsArray(),
            'droppedAttributesCount' => $this->droppedAttributesCount,
        ];
    }
}

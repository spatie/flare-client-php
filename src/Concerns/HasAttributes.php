<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Support\OpenTelemetryAttributeMapper;

trait HasAttributes
{
    public array $attributes = [];

    public int $droppedAttributesCount = 0;

    protected static ?OpenTelemetryAttributeMapper $openTelemetryAttributesMapper = null;

    public function addAttribute(string $key, mixed $value): static
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    public function addAttributes(?array $attributes): static
    {
        if ($attributes === null) {
            return $this;
        }

        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function attributesAsArray(): array
    {
        self::$openTelemetryAttributesMapper ??= new OpenTelemetryAttributeMapper();

        return self::$openTelemetryAttributesMapper->attributesToOpenTelemetry($this->attributes);
    }

    public function dropAttributes(): static
    {
        $this->droppedAttributesCount += count($this->attributes);
        $this->attributes = [];

        return $this;
    }

    public function increaseDroppedAttributes(): static
    {
        $this->droppedAttributesCount++;

        return $this;
    }
}

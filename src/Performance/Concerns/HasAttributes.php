<?php

namespace Spatie\FlareClient\Performance\Concerns;

trait HasAttributes
{
    public array $attributes;

    public int $droppedAttributesCount = 0;

    public function setAttributes(array $attributes): void
    {
        $this->attributes = $attributes;
    }

    public function addAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function addAttributes(array $attributes): static
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    public function attributesToArray(): array
    {
        // TODO: technically not otel: https://github.com/open-telemetry/opentelemetry-proto/blob/main/examples/trace.json
        return $this->attributes;
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

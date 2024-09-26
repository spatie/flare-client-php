<?php

namespace Spatie\FlareClient\Concerns;


trait HasAttributes
{
    public array $attributes = [];

    public int $droppedAttributesCount = 0;

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

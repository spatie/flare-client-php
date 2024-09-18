<?php

namespace Spatie\FlareClient\Contracts;

interface WithAttributes
{
    public function addAttribute(string $key, mixed $value): static;

    public function addAttributes(?array $attributes): static;

    public function dropAttributes(): static;

    public function increaseDroppedAttributes(): static;
}

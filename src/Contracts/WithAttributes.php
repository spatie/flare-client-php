<?php

namespace Spatie\FlareClient\Contracts;

interface WithAttributes
{
    public function addAttribute(string $key, mixed $value): void;

    public function addAttributes(?array $attributes): static;

    public function attributesAsArray(): array;

    public function dropAttributes(): static;

    public function increaseDroppedAttributes(): static;
}

<?php

namespace Spatie\FlareClient\Contracts;

interface RequestAttributesProvider extends AttributesProvider
{
    public function url(): string;

    public function path(): ?string;

    public function method(): string;
}

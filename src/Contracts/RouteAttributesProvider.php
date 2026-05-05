<?php

namespace Spatie\FlareClient\Contracts;

interface RouteAttributesProvider extends AttributesProvider
{
    public function route(): ?string;

    public function method(): string;
}

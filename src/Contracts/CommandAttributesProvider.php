<?php

namespace Spatie\FlareClient\Contracts;

interface CommandAttributesProvider extends AttributesProvider
{
    public function command(): string;

    public function commandClass(): ?string;
}

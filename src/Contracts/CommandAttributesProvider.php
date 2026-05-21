<?php

namespace Spatie\FlareClient\Contracts;

interface CommandAttributesProvider extends AttributesProvider, EntryPointHandlerProvider
{
    public function command(): string;

    public function commandClass(): ?string;
}

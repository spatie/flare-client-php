<?php

namespace Spatie\FlareClient\Contracts;

interface EntryPointHandlerProvider
{
    public function entryPointHandlerName(): ?string;

    public function entryPointHandlerType(): ?string;

    public function entryPointHandlerIdentifier(): ?string;
}

<?php

namespace Spatie\FlareClient\Contracts;

interface AttributesProvider
{
    /** @return array<string, mixed> */
    public function toArray(): array;
}

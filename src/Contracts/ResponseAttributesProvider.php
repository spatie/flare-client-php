<?php

namespace Spatie\FlareClient\Contracts;

interface ResponseAttributesProvider extends AttributesProvider
{
    public function statusCode(): ?int;
}

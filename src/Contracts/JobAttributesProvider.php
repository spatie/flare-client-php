<?php

namespace Spatie\FlareClient\Contracts;

interface JobAttributesProvider extends AttributesProvider
{
    public function jobName(): string;

    public function jobClass(): ?string;
}

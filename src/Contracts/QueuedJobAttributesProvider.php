<?php

namespace Spatie\FlareClient\Contracts;

interface QueuedJobAttributesProvider extends AttributesProvider
{
    public function jobName(): string;

    public function jobClass(): ?string;
}

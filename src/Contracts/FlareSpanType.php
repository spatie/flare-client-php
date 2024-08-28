<?php

namespace Spatie\FlareClient\Contracts;

/**
 * @property-read string $value
 */
interface FlareSpanType
{
    public function humanReadable(): string;
}

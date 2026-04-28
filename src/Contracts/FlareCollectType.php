<?php

namespace Spatie\FlareClient\Contracts;

/**
 * @property string $value
 */
interface FlareCollectType
{
    public function resolvesEntryPoint(): bool;
}

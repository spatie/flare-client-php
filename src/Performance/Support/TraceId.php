<?php

namespace Spatie\FlareClient\Performance\Support;

class TraceId
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}

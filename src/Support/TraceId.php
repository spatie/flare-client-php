<?php

namespace Spatie\FlareClient\Support;

class TraceId
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(16));
    }
}

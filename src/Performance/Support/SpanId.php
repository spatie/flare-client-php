<?php

namespace Spatie\FlareClient\Performance\Support;

class SpanId
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(8));
    }
}

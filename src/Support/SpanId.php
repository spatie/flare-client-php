<?php

namespace Spatie\FlareClient\Support;

class SpanId
{
    public static function generate(): string
    {
        return bin2hex(random_bytes(8));
    }
}

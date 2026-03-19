<?php

namespace Spatie\FlareDaemon\Support;

use RuntimeException;

class Json
{
    public static function encode(mixed $payload): string
    {
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);

        if ($encoded === false) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $encoded;
    }

    public static function decode(string $payload): mixed
    {
        $decoded = json_decode($payload, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(json_last_error_msg());
        }

        return $decoded;
    }

    public static function decodeLoose(string $payload): mixed
    {
        if ($payload === '') {
            return null;
        }

        $decoded = json_decode($payload, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $payload;
    }
}

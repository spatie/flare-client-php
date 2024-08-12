<?php

namespace Spatie\FlareClient\Time;

class Duration
{
    public static function minute(bool $asNano = false): int
    {
        return self::normalize(self::minutes(1),  $asNano);
    }

    public static function minutes(int $minutes, bool $asNano = false): int
    {
        return self::normalize($minutes * 60 * 1000_000,  $asNano);
    }

    public static function second(bool $asNano = false): int
    {
        return self::normalize(self::seconds(1),  $asNano);
    }

    public static function seconds(int $seconds, bool $asNano = false): int
    {
        return self::normalize($seconds * 1000_000,  $asNano);
    }

    public static function millisecond(bool $asNano = false): int
    {
        return self::normalize(self::milliseconds(1),  $asNano);
    }

    public static function milliseconds(int $milliseconds, bool $asNano = false): int
    {
        return self::normalize($milliseconds * 1000,  $asNano);
    }

    public static function microsecond(bool $asNano = false): int
    {
        return self::normalize(self::microseconds(1),  $asNano);
    }

    public static function microseconds(int $microseconds, bool $asNano = false): int
    {
        return self::normalize($microseconds,  $asNano);
    }

    protected static function normalize(
        int $microseconds,
        bool $asNano
    ): int {
        return $asNano ? $microseconds * 1000 : $microseconds;
    }
}

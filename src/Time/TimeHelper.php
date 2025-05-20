<?php

namespace Spatie\FlareClient\Time;

use DateTimeInterface;

class TimeHelper
{
    public static function minute(): int
    {
        return self::minutes(1);
    }

    public static function minutes(int|float $minutes): int
    {
        return (int) ($minutes * 60 * 1000_000_000);
    }

    public static function second(): int
    {
        return self::seconds(1);
    }

    public static function seconds(int|float $seconds): int
    {
        return (int) ($seconds * 1000_000_000);
    }

    public static function millisecond(): int
    {
        return self::milliseconds(1);
    }

    public static function milliseconds(int|float $milliseconds): int
    {
        return (int) ($milliseconds * 1000_000);
    }

    public static function microsecond(): int
    {
        return self::microseconds(1);
    }

    public static function microseconds(int|float $microseconds): int
    {
        return (int) ($microseconds * 1_000);
    }

    public static function phpMicroTime(int|float $microtime): int
    {
        return (int) ($microtime * 1000_000_000);
    }

    public static function dateTimeToNano(DateTimeInterface $dateTime): int
    {
        return (int) $dateTime->format('U') * 1_000_000_000 + (int) $dateTime->format('u') * 1_000;
    }

    public static function now(): int
    {
        return static::phpMicroTime(microtime(true));
    }
}

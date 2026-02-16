<?php

namespace Spatie\FlareClient\Tests\Shared;

use DateTimeImmutable;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Time\Time;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder as LaravelQueryRecorder;
use Spatie\LaravelFlare\Recorders\RedisCommandRecorder\RedisCommandRecorder as LaravelRedisCommandRecorder;

class FakeTime implements Time
{
    protected static ?int $time = null;

    protected static ?self $instance = null;

    public static function isSetup(): bool
    {
        return static::$instance !== null;
    }

    public static function setup(string|DateTimeImmutable|int $dateTime): self
    {
        static::$instance ??= new self();

        static::setCurrentTime($dateTime);

        return static::$instance;
    }

    public function __construct()
    {
    }

    public function getCurrentTime(): int
    {
        return static::$time ?? 0; // Nano seconds
    }

    public static function setCurrentTime(string|DateTimeImmutable|int $dateTime): void
    {
        if(static::isSetup() === false){
            static::setup($dateTime);

            return;
        }

        if (is_string($dateTime)) {
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime);
        }

        if($dateTime instanceof DateTimeImmutable){
            $dateTime = $dateTime->getTimestamp() * 1000_000_000;
        }

        static::$time = $dateTime;
    }

    public static function advance(int $seconds): void
    {
        if (static::isSetup() === false) {
            throw new \Exception('FakeTime is not setup. Call FakeTime::setup() first.');
        }

        static::$time += $seconds * 1000_000_000;
    }

    public static function reset(): void
    {
        static::$instance = null;
        static::$time = null;
    }
}

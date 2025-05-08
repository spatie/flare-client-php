<?php

namespace Spatie\FlareClient\Tests\Shared;

use DateTimeImmutable;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\FlareClient\Time\Time;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder as LaravelQueryRecorder;
use Spatie\LaravelFlare\Recorders\RedisCommandRecorder\RedisCommandRecorder as LaravelRedisCommandRecorder;

class FakeTime implements Time
{
    protected static ?DateTimeImmutable $dateTime = null;

    protected static ?self $instance = null;

    public static function isSetup(): bool
    {
        return static::$instance !== null;
    }

    public static function setup(string|DateTimeImmutable $dateTime): self
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
        return static::$dateTime->getTimestamp() * 1000_000_000; // Nano seconds
    }

    public static function setCurrentTime(string|DateTimeImmutable $dateTime): void
    {
        if(static::isSetup() === false){
            static::setup($dateTime);

            return;
        }

        if (is_string($dateTime)) {
            $dateTime = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dateTime);
        }

        static::$dateTime = $dateTime;
    }

    public static function reset()
    {
        static::$instance = null;
        static::$dateTime = null;
    }
}

<?php

namespace Spatie\FlareClient\Tests\Shared;

use Exception;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\Recorders\GlowRecorder\GlowSpanEvent;
use Spatie\LaravelFlare\Recorders\QueryRecorder\QueryRecorder as LaravelQueryRecorder;
use Spatie\LaravelFlare\Recorders\RedisCommandRecorder\RedisCommandRecorder as LaravelRedisCommandRecorder;

class FakeMemory implements Memory
{
    protected static array $nextMemoryUsages = [];

    protected static ?self $instance = null;

    public static function isSetup(): bool
    {
        return static::$instance !== null;
    }

    public static function setup(): self
    {
        static::$instance ??= new self();

        return static::$instance;
    }

    public function __construct()
    {
    }

    public function getPeakMemoryUsage(): int
    {
        return array_shift(static::$nextMemoryUsages) ?? throw new Exception("No more fake memory usages left");
    }

    public function resetPeaMemoryUsage(): void
    {

    }

    public function nextMemoryUsage(int $bytes): self
    {
        static::$nextMemoryUsages[] = $bytes;

        return $this;
    }

    public static function reset(): void
    {
        static::$instance = null;
        static::$nextMemoryUsages = [];
    }
}

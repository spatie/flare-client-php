<?php

namespace Spatie\FlareClient\Tests\Shared\Samplers;

use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;

class TestSampler implements Sampler
{
    public static float $sampleRate;

    public static function alwaysSample(): void
    {
        static::$sampleRate = 1;
    }

    public static function neverSample(): void
    {
        static::$sampleRate = 0;
    }

    public static function sampleRate(float $sampleRate): void
    {
        static::$sampleRate = $sampleRate;
    }

    public function shouldSample(array $context): bool
    {
        return (new RateSampler(['rate' => static::$sampleRate]))->shouldSample($context);
    }
}

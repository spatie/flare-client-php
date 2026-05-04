<?php

namespace Spatie\FlareClient\Tests\Shared\Samplers;

use Spatie\FlareClient\Sampling\RateSampler;

class FakeSampler extends RateSampler
{
    /** @var float[] */
    public static array $nextRandoms = [];

    public static function setRandoms(float ...$randoms): void
    {
        static::$nextRandoms = $randoms;
    }

    public static function reset(): void
    {
        static::$nextRandoms = [];
    }

    protected function random(): float
    {
        if (empty(static::$nextRandoms)) {
            return parent::random();
        }

        return array_shift(static::$nextRandoms);
    }
}

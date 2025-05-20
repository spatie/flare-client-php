<?php

namespace Spatie\FlareClient\Sampling;

class RateSampler implements Sampler
{
    protected float $rate;

    public function __construct(
        array $config,
    ) {
        $rate = $config['rate'] ?? 0.1;

        if ($rate < 0 || $rate > 1) {
            throw new \InvalidArgumentException('Rate must be between 0 and 1');
        }

        $this->rate = $rate;
    }

    public function shouldSample(array $context): bool
    {
        return mt_rand(0, 100) / 100 <= $this->rate;
    }
}

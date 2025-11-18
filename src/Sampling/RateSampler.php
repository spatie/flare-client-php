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
        if ($this->rate === 0.0) {
            return false;
        }

        if ($this->rate === 1.0) {
            return true;
        }

        return (mt_rand() / mt_getrandmax()) < $this->rate;
    }
}

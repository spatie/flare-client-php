<?php

namespace Spatie\FlareClient\Sampling;

class RateSampler implements Sampler
{
    public function __construct(
        protected float $rate,
    )
    {
        if($rate < 0 || $rate > 1) {
            throw new \InvalidArgumentException('Rate must be between 0 and 1');
        }
    }

    public function shouldSample(SamplingContext $context): bool
    {
        // TODO: add a laravel lottery sampler
        // TODO: add support for propagation
        // TODO: do not sample testing purposes + local?
        // TODO: make sure we slow down sampling from the server when too much?

        return mt_rand(0, 100) / 100 <= $this->rate;
    }
}

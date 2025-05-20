<?php

namespace Spatie\FlareClient\Sampling;

class AlwaysSampler implements Sampler
{
    public function shouldSample(array $context): bool
    {
        return true;
    }
}

<?php

namespace Spatie\FlareClient\Sampling;

class NeverSampler implements Sampler
{
    public function shouldSample(array $context): bool
    {
        return false;
    }
}

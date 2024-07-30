<?php

namespace Spatie\FlareClient\Sampling;

class AlwaysSampler implements Sampler
{
    public function shouldSample(SamplingContext $context): bool
    {
        return true;
    }
}

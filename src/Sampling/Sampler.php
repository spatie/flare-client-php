<?php

namespace Spatie\FlareClient\Sampling;

interface Sampler
{
    public function shouldSample(SamplingContext $context): bool;
}

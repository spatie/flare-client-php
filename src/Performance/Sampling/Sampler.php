<?php

namespace Spatie\FlareClient\Performance\Sampling;

interface Sampler
{
    public function shouldSample(SamplingContext $context): bool;
}

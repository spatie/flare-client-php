<?php

namespace Spatie\FlareClient\Sampling;

interface Sampler
{
    public function shouldSample(array $context): bool;
}

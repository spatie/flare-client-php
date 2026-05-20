<?php

namespace Spatie\FlareClient\Sampling;

use Spatie\FlareClient\EntryPoint\EntryPoint;

class AlwaysSampler implements Sampler
{
    public function shouldSample(EntryPoint $entryPoint, ?bool $parentSampled = null): bool
    {
        return true;
    }
}

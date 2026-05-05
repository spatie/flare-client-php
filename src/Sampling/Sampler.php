<?php

namespace Spatie\FlareClient\Sampling;

use Spatie\FlareClient\EntryPoint\EntryPoint;

interface Sampler
{
    public function shouldSample(EntryPoint $entryPoint): bool;
}

<?php

namespace Spatie\FlareClient\Sampling;

use Spatie\FlareClient\EntryPoint\EntryPoint;

interface DeferrableSampler extends Sampler
{
    public function isPending(): bool;

    public function reevaluate(EntryPoint $entryPoint): bool;

    public function reset(): void;
}

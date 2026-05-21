<?php

namespace Spatie\FlareClient\Sampling\Rules;

use Closure;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\SamplingRule;

class DeferredClosureSamplingRule extends SamplingRule implements DeferredSamplerRule
{
    /** @param Closure(EntryPoint):?float $closure */
    public function __construct(protected Closure $closure)
    {
    }

    public function appliesTo(EntryPointType $entryPointType): bool
    {
        return true;
    }

    public function getMatchedRate(EntryPoint $entryPoint): ?float
    {
        return ($this->closure)($entryPoint);
    }
}

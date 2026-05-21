<?php

namespace Spatie\FlareClient\Sampling\Rules;

use Closure;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\SamplingRule;

class ClosureSamplingRule extends SamplingRule
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

<?php

namespace Spatie\FlareClient\Sampling\Rules;

use InvalidArgumentException;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Support\PatternMatcher;

class CommandSamplingRule extends SamplingRule implements DeferredSamplerRule
{
    public function __construct(
        protected string $pattern,
        protected float $rate,
    ) {
        if ($rate < 0 || $rate > 1) {
            throw new InvalidArgumentException('Sampling rate must be between 0 and 1.');
        }
    }

    public function appliesTo(EntryPointType $entryPointType): bool
    {
        return $entryPointType === EntryPointType::Cli;
    }

    public function getMatchedRate(EntryPoint $entryPoint): ?float
    {
        return PatternMatcher::matches($entryPoint->handlerIdentifier, $this->pattern) ? $this->rate : null;
    }
}

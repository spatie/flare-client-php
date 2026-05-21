<?php

namespace Spatie\FlareClient\Sampling\Rules;

use InvalidArgumentException;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Sampling\DeferredSamplerRule;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Support\PatternMatcher;

class RouteSamplingRule extends SamplingRule implements DeferredSamplerRule
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
        return $entryPointType === EntryPointType::Web;
    }

    public function getMatchedRate(EntryPoint $entryPoint): ?float
    {
        $value = str_contains($entryPoint->handlerIdentifier, ' ')
            ? explode(' ', $entryPoint->handlerIdentifier, 2)[1]
            : $entryPoint->handlerIdentifier;

        return PatternMatcher::matches($value, $this->pattern) ? $this->rate : null;
    }
}

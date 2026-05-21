<?php

namespace Spatie\FlareClient\Sampling\Rules;

use InvalidArgumentException;
use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Sampling\SamplingRule;
use Spatie\FlareClient\Support\PatternMatcher;

class PathSamplingRule extends SamplingRule
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
        $path = parse_url($entryPoint->value, PHP_URL_PATH) ?: '/';

        $value = rtrim($path, '/') ?: '/';
        $pattern = rtrim($this->pattern, '/') ?: '/';

        return PatternMatcher::matches($value, $pattern) ? $this->rate : null;
    }
}

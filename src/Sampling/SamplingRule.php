<?php

namespace Spatie\FlareClient\Sampling;

use Closure;
use InvalidArgumentException;
use Spatie\FlareClient\EntryPoint\EntryPoint;
use Spatie\FlareClient\Support\PatternMatcher;

class SamplingRule
{
    protected function __construct(
        protected SamplingRuleType $type,
        protected ?string $pattern,
        protected ?float $rate,
        protected ?Closure $closure = null,
    ) {
        if ($rate !== null && ($rate < 0 || $rate > 1)) {
            throw new InvalidArgumentException('Sampling rate must be between 0 and 1.');
        }
    }

    public static function forUrl(string $pattern, float $rate): static
    {
        return new static(SamplingRuleType::Url, $pattern, $rate);
    }

    public static function forPath(string $pattern, float $rate): static
    {
        return new static(SamplingRuleType::Path, $pattern, $rate);
    }

    public static function forRoute(string $pattern, float $rate): static
    {
        return new static(SamplingRuleType::Route, $pattern, $rate);
    }

    public static function forCommand(string $pattern, float $rate): static
    {
        return new static(SamplingRuleType::Command, $pattern, $rate);
    }

    public static function forJob(string $pattern, float $rate): static
    {
        return new static(SamplingRuleType::Job, $pattern, $rate);
    }

    /** @param $closure Closure(EntryPoint):?float  */
    public static function using(Closure $closure): static
    {
        return new static(SamplingRuleType::Closure, null, null, $closure);
    }

    /** @param $closure Closure(EntryPoint):?float  */
    public static function usingEarly(Closure $closure): static
    {
        return new static(SamplingRuleType::EarlyClosure, null, null, $closure);
    }

    public static function fromArray(array $data): static
    {
        if (! isset($data['type']) || ! isset($data['rate']) || ! isset($data['pattern'])) {
            throw new InvalidArgumentException('Sampling rule array must contain "type", "pattern" and "rate" keys.');
        }

        if (! $data['type'] instanceof SamplingRuleType) {
            throw new InvalidArgumentException('Sampling rule "type" must be a SamplingRuleType enum.');
        }

        if ($data['type'] === SamplingRuleType::Closure || $data['type'] === SamplingRuleType::EarlyClosure) {
            throw new InvalidArgumentException('Closure sampling rules cannot be created from arrays.');
        }

        return new static($data['type'], $data['pattern'], $data['rate']);
    }

    public function type(): SamplingRuleType
    {
        return $this->type;
    }

    public function canRun(EntryPoint $entryPoint): bool
    {
        return match ($this->type) {
            SamplingRuleType::Url, SamplingRuleType::Path, SamplingRuleType::Job, SamplingRuleType::EarlyClosure => true,
            SamplingRuleType::Route, SamplingRuleType::Command, SamplingRuleType::Closure => $entryPoint->handlerResolved,
        };
    }

    public function getMatchedRate(EntryPoint $entryPoint): ?float
    {
        if ($this->type === SamplingRuleType::Closure || $this->type === SamplingRuleType::EarlyClosure) {
            /** @var Closure $closure */
            $closure = $this->closure;

            return $closure($entryPoint);
        }

        /** @var string $pattern */
        $pattern = $this->pattern;

        $value = match ($this->type) {
            SamplingRuleType::Url => $entryPoint->value,
            SamplingRuleType::Path => parse_url($entryPoint->value, PHP_URL_PATH) ?: '/',
            SamplingRuleType::Route => str_contains($entryPoint->handlerIdentifier, ' ')
                ? explode(' ', $entryPoint->handlerIdentifier, 2)[1]
                : $entryPoint->handlerIdentifier,
            default => $entryPoint->handlerIdentifier,
        };

        if ($this->type === SamplingRuleType::Path || $this->type === SamplingRuleType::Url) {
            $value = rtrim($value, '/') ?: '/';
            $pattern = rtrim($pattern, '/') ?: '/';
        }

        return PatternMatcher::matches($value, $pattern) ? $this->rate : null;
    }
}

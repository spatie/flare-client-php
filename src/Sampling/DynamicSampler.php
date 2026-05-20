<?php

namespace Spatie\FlareClient\Sampling;

use Spatie\FlareClient\EntryPoint\EntryPoint;

class DynamicSampler extends RateSampler implements DeferrableSampler
{
    /** @var array<SamplingRule> */
    protected array $rules;

    protected bool $pending = false;

    protected ?bool $parentSampled = null;

    public function __construct(array $config)
    {
        parent::__construct(['rate' => $config['base_rate'] ?? null]);

        $this->rules = array_map(
            fn (SamplingRule|array $rule) => $rule instanceof SamplingRule
                ? $rule
                : SamplingRule::fromArray($rule),
            $config['rules'] ?? [],
        );
    }

    public function shouldSample(EntryPoint $entryPoint, ?bool $parentSampled = null): bool
    {
        $this->pending = false;
        $this->parentSampled = $parentSampled;

        foreach ($this->rules as $rule) {
            if (! $rule->type()->appliesTo($entryPoint->type)) {
                continue;
            }

            if (! $rule->canRun($entryPoint)) {
                $this->pending = true;

                break;
            }

            $rate = $rule->getMatchedRate($entryPoint);

            if ($rate !== null) {
                return $this->decide($rate);
            }
        }

        if ($this->pending) {
            return true;
        }

        return $parentSampled ?? parent::shouldSample($entryPoint, null);
    }

    public function isPending(): bool
    {
        return $this->pending;
    }

    public function reevaluate(EntryPoint $entryPoint): bool
    {
        $this->pending = false;

        foreach ($this->rules as $rule) {
            if (! $rule->type()->appliesTo($entryPoint->type)) {
                continue;
            }

            if (! $rule->canRun($entryPoint)) {
                continue;
            }

            $rate = $rule->getMatchedRate($entryPoint);

            if ($rate !== null) {
                return $this->decide($rate);
            }
        }

        return $this->parentSampled ?? parent::shouldSample($entryPoint, null);
    }

    public function reset(): void
    {
        $this->pending = false;
        $this->parentSampled = null;
    }
}

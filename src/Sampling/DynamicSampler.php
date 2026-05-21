<?php

namespace Spatie\FlareClient\Sampling;

use Spatie\FlareClient\EntryPoint\EntryPoint;

class DynamicSampler extends RateSampler implements DeferrableSampler
{
    /** @var array<SamplingRule> */
    protected array $rules;

    protected bool $deferred = false;

    protected ?bool $parentSampled = null;

    /** @param array{base_rate?: float|null, rules?: array<SamplingRule>} $config */
    public function __construct(array $config)
    {
        parent::__construct(['rate' => $config['base_rate'] ?? null]);

        $this->rules = $config['rules'] ?? [];
    }

    public function shouldSample(EntryPoint $entryPoint, ?bool $parentSampled = null): bool
    {
        $this->deferred = false;
        $this->parentSampled = $parentSampled;

        foreach ($this->rules as $rule) {
            if (! $rule->appliesTo($entryPoint->type)) {
                continue;
            }

            if ($rule instanceof DeferredSamplerRule && ! $entryPoint->handlerResolved) {
                $this->deferred = true;

                break;
            }

            $rate = $rule->getMatchedRate($entryPoint);

            if ($rate !== null) {
                return $this->decide($rate);
            }
        }

        if ($this->deferred) {
            return true;
        }

        return $parentSampled ?? parent::shouldSample($entryPoint, null);
    }

    public function isDeferred(): bool
    {
        return $this->deferred;
    }

    public function reevaluate(EntryPoint $entryPoint): bool
    {
        $this->deferred = false;

        foreach ($this->rules as $rule) {
            if (! $rule->appliesTo($entryPoint->type)) {
                continue;
            }

            if ($rule instanceof DeferredSamplerRule && ! $entryPoint->handlerResolved) {
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
        $this->deferred = false;
        $this->parentSampled = null;
    }
}

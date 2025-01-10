<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Exception;
use ReflectionProperty;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Flare;
use Spatie\FlareClient\Tracer;

class ExpectTracer
{
    protected Tracer $tracer;

    protected int $traceAssertCounter = 0;

    public static function create(
        Flare $flare
    ): self {
        return new self($flare);
    }

    public function __construct(
        protected Flare $flare
    ) {
        $this->tracer = $this->flare->tracer;
    }

    public function hasTraceCount(int $count): self
    {
        expect($this->tracer->getTraces())->toHaveCount($count);

        return $this;
    }

    public function isSampling(): self
    {
        expect($this->tracer->samplingType)->toEqual(SamplingType::Sampling);

        return $this;
    }

    public function isWaiting(): self
    {
        expect($this->tracer->samplingType)->toEqual(SamplingType::Waiting);

        return $this;
    }

    public function isOff(): self
    {
        expect($this->tracer->samplingType)->toEqual(SamplingType::Off);

        return $this;
    }

    public function isDisabled(): self
    {
        expect($this->tracer->samplingType)->toEqual(SamplingType::Disabled);

        return $this;
    }

    /**
     * @param Closure(ExpectTrace): ExpectTrace $closure
     */
    public function trace(Closure $closure): self
    {
        $trace = array_values($this->tracer->getTraces())[$this->traceAssertCounter] ?? null;

        if ($trace === null) {
            throw new Exception('Trace is not recorded');
        }

        $closure(new ExpectTrace($trace));

        $this->traceAssertCounter++;

        return $this;
    }

    /**
     * @param Closure(ExpectResource): ExpectResource $closure
     */
    public function resource(Closure $closure): self
    {
        $reflection = new ReflectionProperty(Tracer::class, 'resource');

        $reflection->setAccessible(true);

        $closure(new ExpectResource($reflection->getValue($this->tracer)));

        return $this;
    }

    /**
     * @param Closure(ExpectScope):ExpectScope $closure
     *
     * @return $this
     */
    public function scope(Closure $closure): self
    {
        $reflection = new ReflectionProperty(Tracer::class, 'scope');

        $reflection->setAccessible(true);

        $closure(new ExpectScope($reflection->getValue($this->tracer)));

        return $this;
    }
}

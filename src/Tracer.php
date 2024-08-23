<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\FlareClient\Concerns\GeneratesIds;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Exporters\JsonExporter;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\TraceLimits;

class Tracer
{
    use UsesTime;
    use GeneratesIds;

    /** @var array<string, Span[]> */
    public array $traces = [];

    protected ?string $currentTraceId = null;

    protected ?string $currentSpanId = null;

    /**
     * @param Api $api
     * @param JsonExporter $exporter
     * @param TraceLimits $limits
     * @param SamplingType $samplingType
     * @param Sampler $sampler
     */
    public function __construct(
        protected readonly Api $api,
        protected readonly JsonExporter $exporter,
        public readonly TraceLimits $limits,
        protected Resource $resource,
        protected Scope $scope,
        public readonly Sampler $sampler = new RateSampler(),
        public SamplingType $samplingType = SamplingType::Waiting,
    ) {
    }

    public function potentialStartTrace(array $context = []): SamplingType
    {
        if ($this->samplingType !== SamplingType::Waiting) {
            return $this->samplingType;
        }

        if (array_key_exists('traceId', $context)
            && $context['traceId'] !== null
            && array_key_exists('spanId', $context)
            && $context['spanId'] !== null
        ) {
            // TODO: initial work for propagation

            $this->currentTraceId = $context['traceId'];
            $this->currentSpanId = $context['spanId'];

            return $this->samplingType = SamplingType::Sampling;
        }

        if (! $this->sampler->shouldSample($context)) {
            return $this->samplingType = SamplingType::Off;
        }

        $this->startTrace();

        return SamplingType::Sampling;
    }

    public function isSampling(): bool
    {
        return $this->samplingType === SamplingType::Sampling;
    }

    public function startTrace(): void
    {
        if ($this->currentTraceId) {
            throw new Exception('Trace already started');
        }

        if ($this->samplingType !== SamplingType::Waiting) {
            throw new Exception('Trace cannot be started when sampling is disabled, off or already started');
        }

        $this->currentTraceId = static::generateIdFor()->trace();
        $this->samplingType = SamplingType::Sampling;
    }

    public function endTrace(): void
    {
        $this->currentTraceId = null;
        $this->samplingType = SamplingType::Waiting;

        // TODO what if we have spans after the trace is ended?

        $payload = $this->exporter->export(
            $this->resource,
            $this->scope,
            $this->traces,
        );

        $this->api->trace($payload);
    }

    public function currentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    /** @return array<Span> */
    public function &currentTrace(): array
    {
        return $this->traces[$this->currentTraceId];
    }

    public function setCurrentSpanId(?string $id): void
    {
        $this->currentSpanId = $id;
    }

    public function currentSpanId(): ?string
    {
        return $this->currentSpanId;
    }

    public function currentSpan(): ?Span
    {
        return $this->traces[$this->currentTraceId][$this->currentSpanId] ?? null;
    }

    public function addSpan(Span $span, bool $makeCurrent = false): Span
    {
        if (count($this->traces[$span->traceId] ?? []) >= $this->limits->maxSpans) {
            return $span;
        }

        $this->traces[$span->traceId][$span->spanId] = $span;

        if ($makeCurrent) {
            $this->setCurrentSpanId($span->spanId);
        }

        return $span;
    }

    public function startSpan(
        string $name,
        array $attributes = [],
    ): Span {
        $span = Span::build(
            traceId: $this->currentTraceId,
            name: $name,
            start: $this::getCurrentTime(),
            parentId: $this->currentSpanId,
            attributes: $attributes,
        );

        return $this->addSpan($span, makeCurrent: true);
    }

    public function endCurrentSpan(?int $endUs = null): void
    {
        $span = $this->currentSpan();

        $span->end = $endUs ?? $this::getCurrentTime();

        $this->setCurrentSpanId($span->parentSpanId);
    }
}

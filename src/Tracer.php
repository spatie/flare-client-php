<?php

namespace Spatie\FlareClient;

use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Exporters\JsonExporter;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\TraceId;
use Spatie\FlareClient\Support\TraceLimits;

class Tracer
{
    use UsesTime;

    /** @var array<string, Span[]> */
    public array $traces = [];

    protected ?string $currentTraceId = null;

    protected ?string $currentSpanId = null;

    public SamplingType $samplingType = SamplingType::Waiting;

    public function __construct(
        protected readonly Api $api,
        protected readonly JsonExporter $exporter,
        public readonly BackTracer $backTracer,
        public readonly Resource $resource,
        public readonly Scope $scope,
        public readonly TraceLimits $limits,
    ) {
    }

    public function send(): void
    {
        $payload = $this->exporter->export(
            $this->resource,
            $this->scope,
            $this->traces,
        );

        $this->api->trace($payload);
    }

    public function skipTrace()
    {
        $this->samplingType = SamplingType::Off;
    }

    public function isSamping(): bool
    {
        return $this->samplingType === SamplingType::Sampling;
    }

    public function startTrace(): void
    {
        if ($this->currentTraceId) {
            throw new \Exception('Trace already started');
        }

        $this->currentTraceId = TraceId::generate();
        $this->samplingType = SamplingType::Sampling;
    }

    public function endCurrentTrace(): void
    {
        $this->currentTraceId = null;
        $this->samplingType = SamplingType::Waiting;
    }

    public function currentTraceId(): string
    {
        return $this->currentTraceId;
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
        if(count($this->traces[$span->traceId] ?? []) >= $this->limits->maxSpans) {
            return $span;
        }

        $this->traces[$span->traceId][$span->spanId] = $span;

        if ($makeCurrent) {
            $this->setCurrentSpanId($span->spanId);
        }

        return $span;
    }

    public function endCurrentSpan(?int $endUs = null): void
    {
        $span = $this->currentSpan();

        $span->endUs = $endUs ?? $this::getCurrentTime();

        $this->setCurrentSpanId($span->parentSpanId);
    }
}

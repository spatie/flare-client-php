<?php

namespace Spatie\FlareClient\Performance;

use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Http\Client;
use Spatie\FlareClient\Performance\Enums\SamplingType;
use Spatie\FlareClient\Performance\Exporters\JsonExporter;
use Spatie\FlareClient\Performance\Resources\Resource;
use Spatie\FlareClient\Performance\Scopes\Scope;
use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Performance\Support\BackTracer;
use Spatie\FlareClient\Performance\Support\TraceId;

class Tracer
{
    use UsesTime;

    /** @var array<string, Span[]> */
    public array $traces = [];

    protected ?string $currentTraceId = null;

    protected ?string $currentSpanId = null;

    public SamplingType $samplingType = SamplingType::Waiting;

    public readonly Resource $resource;

    public readonly Scope $scope;

    public function __construct(
        protected Client $client,
        protected JsonExporter $exporter,
        public readonly BackTracer $backTracer
    ) {
        $this->resource = Resource::build(
            config('app.name'),
            config('app.version'),
        )->host();

        $this->scope = Scope::build();
    }

    public function send(): void
    {
        $payload = $this->exporter->export(
            $this->resource,
            $this->scope,
            $this->traces,
        );

        $this->client->post('traces', $payload);
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
        $this->traces[$span->traceId][$span->id] = $span;

        if ($makeCurrent) {
            $this->setCurrentSpanId($span->id);
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

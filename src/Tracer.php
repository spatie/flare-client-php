<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\FlareClient\Concerns\UsesIds;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\FlareClient\TraceExporters\TraceExporter;

class Tracer
{
    use UsesTime;
    use UsesIds;

    /** @var array<string, Span[]> */
    protected array $traces = [];

    protected ?string $currentTraceId = null;

    protected ?string $currentSpanId = null;

    /**
     * @param Closure(Span):void|null $configureSpansCallable
     * @param Closure(SpanEvent):void|null $configureSpanEventsCallable
     */
    public function __construct(
        protected readonly Api $api,
        protected readonly TraceExporter $exporter,
        public readonly TraceLimits $limits,
        protected Resource $resource,
        protected Scope $scope,
        public readonly Sampler $sampler = new RateSampler([]),
        public ?Closure $configureSpansCallable = null,
        public ?Closure $configureSpanEventsCallable = null,
        public SamplingType $samplingType = SamplingType::Waiting,
        public bool $clearTracesAfterExport = true,
    ) {
    }

    /**
     * @param array{traceparent?: string} $context
     */
    public function potentialStartTrace(array $context = []): SamplingType
    {
        if ($this->samplingType !== SamplingType::Waiting) {
            return $this->samplingType;
        }

        if (array_key_exists('traceparent', $context)) {
            return $this->potentiallyResumeTrace($context['traceparent']);
        }

        if (! $this->sampler->shouldSample($context)) {
            return $this->samplingType = SamplingType::Off;
        }

        $this->startTrace();

        return SamplingType::Sampling;
    }

    public function potentiallyResumeTrace(
        string $traceParent
    ): SamplingType {
        $parsed = static::ids()->parseTraceParent($traceParent);

        if ($parsed === null) {
            return $this->samplingType = SamplingType::Off;
        }

        [
            'traceId' => $traceId,
            'parentSpanId' => $parentSpanId,
            'sampling' => $sampling,
        ] = $parsed;

        if ($sampling === false) {
            return $this->samplingType = SamplingType::Off;
        }

        $this->currentTraceId = $traceId;
        $this->currentSpanId = $parentSpanId;

        return $this->samplingType = SamplingType::Sampling;
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

        $this->currentTraceId = static::ids()->trace();
        $this->samplingType = SamplingType::Sampling;
    }

    public function endTrace(): void
    {
        $this->currentTraceId = null;
        $this->samplingType = SamplingType::Waiting;

        $payload = $this->exporter->export(
            $this->resource,
            $this->scope,
            $this->traces,
        );

        $this->api->trace($payload);

        if ($this->clearTracesAfterExport) {
            $this->traces = [];
        }
    }

    public function currentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    public function traceParent(): string
    {
        return static::ids()->traceParent(
            $this->currentTraceId ?? '',
            $this->currentSpanId ?? '',
            $this->isSampling(),
        );
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

    public function hasCurrentSpan(?FlareSpanType $spanType = null): bool
    {
        $currentSpan = $this->currentSpan();

        if ($currentSpan === null) {
            return false;
        }

        if ($spanType === null) {
            return true;
        }

        $type = $currentSpan->attributes['flare.span_type'] ?? null;

        if ($type === null) {
            return false;
        }

        if ($type instanceof FlareSpanType) {
            $type = $type->value;
        }

        return $type === $spanType->value;
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

        if ($span->end !== null) {
            $this->configureSpan($span);
        }

        return $span;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function startSpan(
        string $name,
        ?int $start = null,
        ?int $end = null,
        array $attributes = [],
    ): Span {
        $span = Span::build(
            traceId: $this->currentTraceId ?? '',
            parentId: $this->currentSpanId,
            name: $name,
            start: $start ?? $this::getCurrentTime(),
            attributes: $attributes,
        );

        $span = $this->addSpan($span, makeCurrent: true);

        if ($end !== null) {
            $this->endSpan($span, $end);
        }

        return $span;
    }

    public function endSpan(?Span $span = null, ?int $endUs = null): Span
    {
        $span ??= $this->currentSpan();

        if ($span === null) {
            throw new Exception('No span to end');
        }

        $span->end = $endUs ?? $this::getCurrentTime();

        $this->configureSpan($span);

        $this->setCurrentSpanId($span->parentSpanId);

        return $span;
    }

    public function trashCurrentTrace(
        SamplingType $samplingType = SamplingType::Waiting
    ): void {
        if ($this->currentTraceId === null) {
            return;
        }

        unset($this->traces[$this->currentTraceId]);

        $this->currentTraceId = null;
        $this->currentSpanId = null;
        $this->samplingType = $samplingType;
    }

    /**
     * @return array<string, Span[]>
     */
    public function getTraces(): array
    {
        return $this->traces;
    }

    protected function configureSpan(Span $span): void
    {
        if ($this->configureSpansCallable) {
            ($this->configureSpansCallable)($span);
        }

        if ($this->configureSpanEventsCallable) {
            foreach ($span->events as $event) {
                ($this->configureSpanEventsCallable)($event);
            }
        }
    }
}

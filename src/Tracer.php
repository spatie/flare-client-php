<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\TraceExporters\TraceExporter;
use Throwable;

class Tracer
{
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
        public readonly Time $time,
        public readonly Ids $ids,
        public readonly Resource $resource,
        public readonly Scope $scope,
        public readonly Sampler $sampler = new RateSampler([]),
        public ?Closure $configureSpansCallable = null,
        public ?Closure $configureSpanEventsCallable = null,
        public ?Closure $filterSpansCallable = null,
        public ?Closure $filterSpanEventsCallable = null,
        public SamplingType $samplingType = SamplingType::Waiting,
        public bool $clearTracesAfterExport = true,
    ) {
    }

    public function startTrace(
        ?string $traceparent = null,
        array $context = [],
        bool $forceSampling = false,
    ): SamplingType {
        if ($forceSampling) {
            return $this->initiateTrace(sample: true);
        }

        if ($this->samplingType !== SamplingType::Waiting) {
            return $this->samplingType;
        }

        $parsedTraceparent = $traceparent !== null
            ? $this->ids->parseTraceparent($traceparent)
            : null;

        if ($parsedTraceparent !== null) {
            [
                'traceId' => $traceId,
                'parentSpanId' => $parentSpanId,
                'sampling' => $sampling,
            ] = $parsedTraceparent;

            return $this->initiateTrace(
                sample: $sampling, traceId: $traceId, spanId: $parentSpanId
            );
        }

        if (! $this->sampler->shouldSample($context)) {
            return $this->initiateTrace(sample: false);
        }

        return $this->initiateTrace(sample: true);
    }

    public function startTraceWithSpan(
        Span|Closure $span,
        array $context = [],
    ): ?Span {
        if (! $this->sampler->shouldSample($context)) {
            $this->initiateTrace(sample: false);

            return null;
        }

        $span = is_callable($span) ? $span() : $span;

        $this->initiateTrace(
            sample: true,
            traceId: $span->traceId,
            spanId: $span->spanId,
        );

        return $span;
    }

    protected function initiateTrace(
        bool $sample,
        ?string $traceId = null,
        ?string $spanId = null,
    ): SamplingType {
        $this->currentTraceId = $traceId ?? $this->ids->trace();
        $this->currentSpanId = $spanId ?? $this->ids->span();

        $this->traces[$this->currentTraceId] = [];

        return $this->samplingType = $sample
            ? SamplingType::Sampling
            : SamplingType::Off;
    }

    public function isSampling(): bool
    {
        return $this->samplingType === SamplingType::Sampling;
    }

    public function endTrace(): void
    {
        $this->currentTraceId = null;
        $this->currentSpanId = null;
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
        return $this->ids->traceParent(
            $this->currentTraceId ?? '',
            $this->currentSpanId ?? '',
            $this->isSampling(),
        );
    }

    /** @return array<Span> */
    public function currentTrace(): array
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

    public function addRawSpan(Span $span): Span
    {
        if (count($this->traces[$span->traceId] ?? []) >= $this->limits->maxSpans) {
            return $span;
        }

        $this->traces[$span->traceId][$span->spanId] = $span;

        $this->setCurrentSpanId($span->spanId);

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
        ?int $time = null,
        array $attributes = [],
    ): ?Span {
        $spanClosure = fn () => new Span(
            traceId: $this->currentTraceId ?? $this->ids->trace(),
            spanId: $this->ids->span(),
            parentSpanId: $this->currentSpanId,
            name: $name,
            start: $time ?? $this->time->getCurrentTime(),
            end: null,
            attributes: $attributes,
        );

        if ($this->isSampling() === true) {
            return $this->addRawSpan($spanClosure());
        }

        $span = $this->startTraceWithSpan($spanClosure);

        if ($span === null) {
            return null; // No sampling was started
        }

        return $this->addRawSpan($span);
    }

    public function endSpan(
        ?Span $span = null,
        ?int $time = null,
        array $additionalAttributes = [],
    ): Span {
        $span ??= $this->currentSpan();

        if ($span === null) {
            throw new Exception('No span to end');
        }

        $span->end = $time ?? $this->time->getCurrentTime();

        if (count($additionalAttributes) > 0) {
            $span->addAttributes($additionalAttributes);
        }

        $this->configureSpan($span);

        $this->setCurrentSpanId($span->parentSpanId);

        return $span;
    }

    /**
     * @param string $name
     * @param Closure $callback
     * @param array<string, mixed> $attributes
     * @param Closure(mixed):array<string, mixed>|null $endAttributes
     */
    public function span(
        string $name,
        Closure $callback,
        array $attributes = [],
        ?Closure $endAttributes = null,
    ): ?Span {
        $span = $this->startSpan($name, attributes: $attributes);

        if ($span === null) {
            return null;
        }

        try {
            $returned = $callback();
        } catch (Throwable $throwable) {
            $this->endSpan($span);

            $span->setStatus(
                SpanStatusCode::Error,
                $throwable->getMessage(),
            );

            throw $throwable;
        }

        $additionalAttributes = $endAttributes === null
            ? []
            : ($endAttributes)($returned);

        $this->endSpan($span, additionalAttributes: $additionalAttributes);

        return $span;
    }

    /** @param array<string, mixed> $attributes */
    public function spanEvent(
        string $name,
        array $attributes = [],
        ?int $timestampUs = null,
    ): ?SpanEvent {
        $currentSpan = $this->currentSpan();

        if ($currentSpan === null) {
            return null;
        }

        $event = new SpanEvent(
            name: $name,
            timestamp: $timestampUs ?? $this->time->getCurrentTime(),
            attributes: $attributes,
        );

        $currentSpan->addEvent(
            $event
        );

        return $event;
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

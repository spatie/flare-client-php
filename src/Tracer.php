<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\GracefulSpanEnder;
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
     * @param Closure(Span):(void|Span)|null $configureSpansCallable
     * @param Closure(SpanEvent):(void|SpanEvent|null)|null $configureSpanEventsCallable
     */
    public function __construct(
        protected readonly Api $api,
        protected readonly TraceExporter $exporter,
        public readonly TraceLimits $limits,
        public readonly Time $time,
        public readonly Ids $ids,
        public readonly Resource $resource,
        public readonly Scope $scope,
        protected ContextRecorder $contextRecorder,
        public readonly Sampler $sampler = new RateSampler([]),
        public ?Closure $configureSpansCallable = null,
        public ?Closure $configureSpanEventsCallable = null,
        public SamplingType $samplingType = SamplingType::Waiting,
        public bool $clearTracesAfterExport = true,
        protected GracefulSpanEnder $gracefulSpanEnder = new GracefulSpanEnder(),
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
                sample: $sampling,
                traceId: $traceId,
                spanId: $parentSpanId
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
        $traceId = $this->currentTraceId;

        $this->currentTraceId = null;
        $this->currentSpanId = null;
        $this->samplingType = SamplingType::Waiting;

        if (empty($this->traces[$traceId] ?? [])) {
            unset($this->traces[$traceId]);

            return;
        }

        $context = $this->contextRecorder->toArray();

        if (! empty($context)) {
            foreach ($this->traces as $spans) {
                $spans[array_key_first($spans)]->addAttributes($context);
            }
        }

        $this->contextRecorder->resetContext();

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
        if ($this->currentTraceId === null) {
            return null;
        }

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

            $this->setCurrentSpanId($span->parentSpanId);
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
        bool $canStartTraces = true,
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

        if ($canStartTraces === false) {
            return null;
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
        array|Closure $additionalAttributes = [],
    ): Span {
        $span ??= $this->currentSpan();

        if ($span === null) {
            throw new Exception('No span to end');
        }

        if ($span->end === null) {
            $span->end = $time ?? $this->time->getCurrentTime();
        }

        if (is_callable($additionalAttributes)) {
            $additionalAttributes = $additionalAttributes();
        }

        if (count($additionalAttributes) > 0) {
            $span->addAttributes($additionalAttributes);
        }

        $this->configureSpan($span);

        $this->setCurrentSpanId($span->parentSpanId);

        return $span;
    }

    /**
     * @template T
     *
     * @param Closure():T $callback
     * @param array<string, mixed> $attributes
     * @param Closure(T):array<string, mixed>|null $endAttributes
     *
     * @return T
     */
    public function span(
        string $name,
        Closure $callback,
        array $attributes = [],
        ?Closure $endAttributes = null,
        bool $canStartTraces = true,
    ): mixed {
        $span = $this->startSpan($name, attributes: $attributes, canStartTraces: $canStartTraces);

        if ($span === null) {
            return $callback();
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

        return $returned;
    }

    /** @param array<string, mixed> $attributes */
    public function spanEvent(
        string $name,
        array $attributes = [],
        ?int $time = null,
    ): ?SpanEvent {
        $currentSpan = $this->currentSpan();

        if ($currentSpan === null) {
            return null;
        }

        $event = new SpanEvent(
            name: $name,
            timestamp: $time ?? $this->time->getCurrentTime(),
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
     * @param Closure(Span):(void|Span) $callback
     */
    public function configureSpansUsing(Closure $callback): static
    {
        $this->configureSpansCallable = $callback;

        return $this;
    }

    /**
     * @param Closure(SpanEvent):(void|SpanEvent|null) $callback
     */
    public function configureSpanEventsUsing(Closure $callback): static
    {
        $this->configureSpanEventsCallable = $callback;

        return $this;
    }

    /**
     * @return array<string, Span[]>
     */
    public function getTraces(): array
    {
        return $this->traces;

    }

    public function gracefullyHandleError(): void
    {
        if ($this->isSampling() === false) {
            return;
        }

        $currentSpan = $this->currentSpan();

        while ($currentSpan !== null) {
            if ($currentSpan->end !== null) {
                break;
            }

            if ($this->gracefulSpanEnder->shouldGracefullyEndSpan($currentSpan)) {
                $this->endSpan($currentSpan);
            }

            $currentSpan = $currentSpan->parentSpanId !== null
                ? $this->traces[$currentSpan->traceId][$currentSpan->parentSpanId] ?? null
                : null;
        }
    }

    protected function configureSpan(Span $span): void
    {
        if ($this->configureSpansCallable) {
            ($this->configureSpansCallable)($span);
        }

        $removedEvent = false;

        if ($this->configureSpanEventsCallable) {
            for ($i = 0; $i < count($span->events); $i++) {
                $event = $span->events[$i];

                $returned = ($this->configureSpanEventsCallable)($event);

                if ($returned === null) {
                    unset($span->events[$i]);
                    $removedEvent = true;
                }
            }
        }

        if ($removedEvent) {
            $span->events = array_values($span->events);
        }
    }
}

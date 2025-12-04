<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\FlareCollectType;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\CollectType;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Resources\Resource;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Scopes\Scope;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Recorders;
use Spatie\FlareClient\Support\TraceLimits;
use Spatie\FlareClient\Time\Time;
use Spatie\FlareClient\Exporters\Exporter;
use Throwable;

class Tracer
{
    /** @var array<Span> */
    protected array $spans = [];

    protected string $currentTraceId;

    protected string $currentSpanId;

    protected bool $currentSpanIdAvailable;

    /**
     * @param Closure(Span):(void|Span)|null $configureSpansCallable
     * @param Closure(SpanEvent):(void|SpanEvent|null)|null $configureSpanEventsCallable
     * @param Closure(Span):(bool)|null $gracefulSpanEnderClosure ,
     */
    public function __construct(
        protected readonly Api $api,
        protected readonly Exporter $exporter,
        public readonly TraceLimits $limits,
        public readonly Time $time,
        public readonly Ids $ids,
        public readonly Resource $resource,
        public readonly Scope $scope,
        protected Recorders $recorders,
        public readonly Sampler $sampler = new RateSampler([]),
        public ?Closure $configureSpansCallable = null,
        public ?Closure $configureSpanEventsCallable = null,
        public bool $sampling = false,
        public readonly bool $disabled = false,
        protected Closure|null $gracefulSpanEnderClosure = null,
    ) {
        $this->currentTraceId = $this->ids->trace();
        $this->currentSpanId = $this->ids->span();
        $this->currentSpanIdAvailable = true;
    }

    public function startTrace(
        ?string $traceId = null,
        ?string $spanId = null,
        ?bool $sample = null,
        array $samplerContext = [],
        ?string $traceParent = null
    ): bool {
        if ($this->disabled === true) {
            return false;
        }

        if ($this->sampling) {
            return $this->sampling;
        }

        if ($traceParent) {
            return $this->startFromTraceparent($traceParent);
        }

        if ($traceId && $spanId && $sample !== null) {
            return $this->startFromDefined(
                sample: $sample,
                traceId: $traceId,
                spanId: $spanId,
                currentSpanIdAvailable: false,
            );
        }

        if ($traceId || $spanId || $sample !== null) {
            throw new Exception("If one of traceId, spanId or sample is provided, all three must be provided.");
        }

        return $this->sampling = $this->sampler->shouldSample($samplerContext);
    }

    protected function startFromTraceparent(
        string $traceParent,
    ): bool {
        $parsedTraceparent = $this->ids->parseTraceparent($traceParent);

        if ($parsedTraceparent === null) {
            return $this->startTrace();
        }

        [
            'traceId' => $traceId,
            'parentSpanId' => $parentSpanId,
            'sampling' => $sampling,
        ] = $parsedTraceparent;

        return $this->startFromDefined(
            sample: $sampling,
            traceId: $traceId,
            spanId: $parentSpanId,
            currentSpanIdAvailable: false
        );
    }

    protected function startFromDefined(
        bool $sample,
        string $traceId,
        string $spanId,
        bool $currentSpanIdAvailable,
    ): bool {
        // TODO: since technically there could already be logs tied to the previous trace_id and span_id
        // It might be useful rewrite those logs to have the new trace_id and span_id

        $this->currentTraceId = $traceId;
        $this->currentSpanId = $spanId;
        $this->currentSpanIdAvailable = $currentSpanIdAvailable;

        $this->spans = [];

        return $this->sampling = $sample;
    }

    public function endTrace(): void
    {
        $traceId = $this->currentTraceId;

        $this->currentTraceId = $this->ids->trace();
        $this->currentSpanId = $this->ids->span();
        $this->currentSpanIdAvailable = true;
        $this->sampling = false;

        if (empty($this->spans)) {
            return;
        }

        /** @var ?ContextRecorder $recorder */
        $recorder = $this->recorders->getRecorder(RecorderType::Context);

        if (($context = $recorder?->toArray()) && count($context) > 0) {
            $this->spans[array_key_first($this->spans)]->addAttributes($context);
        }

        $payload = $this->exporter->traces(
            $this->resource,
            $this->scope,
            [$traceId => $this->spans],
        );

        $this->api->trace($payload);

        $this->spans = [];
    }

    public function trashTrace(): void
    {
        if ($this->sampling === false) {
            return;
        }

        $this->currentTraceId = $this->ids->trace();
        $this->currentSpanId = $this->ids->span();
        $this->currentSpanIdAvailable = true;
        $this->sampling = false;
        $this->spans = [];
    }

    public function isSampling(): bool
    {
        return $this->sampling;
    }


    public function currentTraceId(): string
    {
        return $this->currentTraceId;
    }

    public function currentSpanId(): string
    {
        return $this->currentSpanId;
    }

    public function currentParentSpanId(): ?string
    {
        return $this->currentSpanIdAvailable
            ? null
            : $this->currentSpanId;
    }

    public function nextSpanId(): string
    {
        if ($this->currentSpanIdAvailable === true) {
            $this->currentSpanIdAvailable = false;

            return $this->currentSpanId;
        }

        return $this->ids->span();
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
        return $this->spans;
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
        return $this->spans[$this->currentSpanId] ?? null;
    }

    public function addSpan(Span $span): Span
    {
        if (count($this->spans) >= $this->limits->maxSpans) {
            return $span;
        }

        $this->spans[$span->spanId] = $span;
        $this->currentSpanId = $span->spanId;

        if ($span->end !== null) {
            $this->configureSpan($span);
            $this->currentSpanId = $span->parentSpanId;
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
    ): Span {
        // Order of operations is important here, do not inline!
        $parentSpanId = $this->currentParentSpanId();
        $spanId = $this->nextSpanId();

        $span = new Span(
            traceId: $this->currentTraceId ?? $this->ids->trace(),
            spanId: $spanId,
            parentSpanId: $parentSpanId,
            name: $name,
            start: $time ?? $this->time->getCurrentTime(),
            end: null,
            attributes: $attributes,
        );

        if ($this->isSampling() === true) {
            return $this->addSpan($span);
        }

        return $span;
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

        $this->currentSpanId = $span->parentSpanId ?? '-';

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
        ?Closure $endAttributes = null
    ): mixed {
        if ($this->sampling === false) {
            return $callback();
        }

        $span = $this->startSpan($name, attributes: $attributes);

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

            if ($this->gracefulSpanEnderClosure === null || ($this->gracefulSpanEnderClosure)($currentSpan)) {
                $this->endSpan($currentSpan);
            }

            $currentSpan = $this->traces[$currentSpan->traceId][$currentSpan->parentSpanId] ?? null;
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

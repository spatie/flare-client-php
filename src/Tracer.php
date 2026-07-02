<?php

namespace Spatie\FlareClient;

use Closure;
use Exception;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\AddSpanResult;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Memory\Memory;
use Spatie\FlareClient\Recorders\ContextRecorder\ContextRecorder;
use Spatie\FlareClient\Sampling\DeferrableSampler;
use Spatie\FlareClient\Sampling\RateSampler;
use Spatie\FlareClient\Sampling\Sampler;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\Ids;
use Spatie\FlareClient\Support\Recorders;
use Spatie\FlareClient\Time\Time;
use Throwable;

class Tracer
{
    public const DEFAULT_MAX_SPANS_LIMIT = 1024;
    public const DEFAULT_MAX_ATTRIBUTES_PER_SPAN_LIMIT = 128;
    public const DEFAULT_MAX_SPAN_EVENTS_PER_SPAN_LIMIT = 128;
    public const DEFAULT_MAX_ATTRIBUTES_PER_SPAN_EVENT_LIMIT = 128;

    public const DEFAULT_COLLECT_ERRORS_WITH_TRACES = true;

    /** @var array<Span> */
    protected array $spans = [];

    /** @var array @param array{max_spans: int, max_attributes_per_span: int, max_span_events_per_span: int, max_attributes_per_span_event: int}|null */
    public readonly array $limits;

    protected ?string $currentTraceId = null;

    protected ?string $currentSpanId = null;

    protected bool $currentSpanIdAvailable = true;

    /**
     * @param array{max_spans: int, max_attributes_per_span: int, max_span_events_per_span: int, max_attributes_per_span_event: int}|null $limits
     * @param Closure(Span):(void|Span)|null $configureSpansCallable
     * @param Closure(SpanEvent):(void|SpanEvent|null)|null $configureSpanEventsCallable
     * @param Closure(Span):(bool)|null $gracefulSpanEnderClosure ,
     */
    public function __construct(
        protected readonly Api $api,
        ?array $limits,
        public readonly Time $time,
        public readonly Ids $ids,
        public readonly Memory $memory,
        protected Recorders $recorders,
        protected readonly EntryPointResolver $entryPointResolver,
        public readonly Sampler $sampler = new RateSampler([]),
        public ?Closure $configureSpansCallable = null,
        public ?Closure $configureSpanEventsCallable = null,
        public bool $sampling = false,
        protected bool $paused = false,
        public readonly bool $disabled = false,
        protected Closure|null $gracefulSpanEnderClosure = null,
    ) {
        $this->limits = [
            'max_spans' => $limits['max_spans'] ?? self::DEFAULT_MAX_SPANS_LIMIT,
            'max_attributes_per_span' => $limits['max_attributes_per_span'] ?? self::DEFAULT_MAX_ATTRIBUTES_PER_SPAN_LIMIT,
            'max_span_events_per_span' => $limits['max_span_events_per_span'] ?? self::DEFAULT_MAX_SPAN_EVENTS_PER_SPAN_LIMIT,
            'max_attributes_per_span_event' => $limits['max_attributes_per_span_event'] ?? self::DEFAULT_MAX_ATTRIBUTES_PER_SPAN_EVENT_LIMIT,
        ];
    }

    public function startTrace(
        ?string $traceId = null,
        ?string $spanId = null,
        ?string $traceParent = null,
    ): bool {
        if ($this->currentTraceId !== null) {
            return $this->sampling;
        }

        if (($traceId === null) !== ($spanId === null)) {
            throw new Exception('If one of traceId or spanId is provided, both must be provided.');
        }

        $parentSampled = null;
        $currentSpanAvailable = true;

        $parsedTraceParent = $traceParent !== null
            ? $this->ids->parseTraceparent($traceParent)
            : null;

        if ($traceId !== null && $spanId !== null) {
            $currentSpanAvailable = false;
        }

        if ($parsedTraceParent) {
            $traceId = $parsedTraceParent['traceId'];
            $spanId = $parsedTraceParent['parentSpanId'];
            $parentSampled = $parsedTraceParent['sampling'];
            $currentSpanAvailable = false;
        }

        $this->currentTraceId = $traceId ?? $this->ids->trace();
        $this->currentSpanId = $spanId ?? $this->ids->span();
        $this->currentSpanIdAvailable = $currentSpanAvailable;

        if ($this->disabled === true) {
            return false;
        }

        return $this->sampling = $this->sampler->shouldSample(
            $this->entryPointResolver->get(),
            $parentSampled,
        );
    }

    public function endTrace(): void
    {
        if ($this->sampler instanceof DeferrableSampler) {
            $this->sampler->reset();
        }

        $this->currentTraceId = null;
        $this->currentSpanId = null;
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

        $this->api->trace($this->spans);

        $this->spans = [];
    }

    public function reevaluateSampling(): void
    {
        if (! $this->sampler instanceof DeferrableSampler || ! $this->sampler->isDeferred()) {
            return;
        }

        $decision = $this->sampler->reevaluate($this->entryPointResolver->get());

        if ($decision === false) {
            $this->unsample();

            return;
        }

        $this->sampling = true;
    }

    public function unsample(): void
    {
        if ($this->sampler instanceof DeferrableSampler) {
            $this->sampler->reset();
        }

        $this->paused = false;
        $this->currentTraceId = null;
        $this->currentSpanId = null;
        $this->currentSpanIdAvailable = true;
        $this->sampling = false;
        $this->spans = [];
    }

    public function pauseSampling(): void
    {
        if ($this->sampling === false) {
            return;
        }

        $this->sampling = false;
        $this->paused = true;
    }

    public function resumeSampling(): void
    {
        if ($this->paused === false) {
            return;
        }

        $this->paused = false;
        $this->sampling = true;
    }

    public function isSampling(): bool
    {
        return $this->sampling;
    }

    public function isSamplingPaused(): bool
    {
        return $this->paused;
    }

    public function currentTraceId(): ?string
    {
        return $this->currentTraceId;
    }

    public function currentSpanId(): ?string
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
        if ($this->sampling && $this->currentSpanIdAvailable === true && $this->currentSpanId !== null) {
            $this->currentSpanIdAvailable = false;

            return $this->currentSpanId;
        }

        return $this->ids->span();
    }

    public function traceParent(): ?string
    {
        if ($this->currentTraceId === null || $this->currentSpanId === null) {
            return null;
        }

        return $this->ids->traceParent(
            $this->currentTraceId,
            $this->currentSpanId,
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
        if ($this->currentSpanId === null) {
            return null;
        }

        return $this->spans[$this->currentSpanId] ?? null;
    }

    public function addSpan(Span $span): Span|AddSpanResult
    {
        if (count($this->spans) >= $this->limits['max_spans']) {
            return AddSpanResult::LimitReached;
        }

        $this->spans[$span->spanId] = $span;
        $this->currentSpanId = $span->spanId;

        if ($span->end !== null) {
            $this->configureSpan($span);

            $this->currentSpanId = $span->parentSpanId ?? '-';
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
    ): Span|AddSpanResult {
        if ($this->currentTraceId === null) {
            throw new Exception('Cannot start a span without an active trace.');
        }

        // Order of operations is important here, do not inline!
        $parentSpanId = $this->currentParentSpanId();
        $spanId = $this->nextSpanId();

        $span = new Span(
            traceId: $this->currentTraceId,
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
        bool $includeMemoryUsage = false,
    ): ?Span {
        if ($this->sampling === false) {
            return null;
        }

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

        // memory_reset_peak_usage() only exists on PHP 8.2+, so peak memory cannot be scoped per span before then
        if ($includeMemoryUsage && PHP_VERSION_ID >= 80200) {
            $span->addAttribute('flare.peak_memory_usage', $this->memory->getPeakMemoryUsage());
        }

        $this->configureSpan($span);

        if (isset($this->spans[$span->spanId])) {
            $this->currentSpanId = $span->parentSpanId ?? '-';
        }

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
        ?int $startTime = null,
        ?int $endTime = null,
        bool $includeMemoryUsage = false,
    ): mixed {
        if ($this->sampling === false) {
            return $callback();
        }

        $span = $this->startSpan($name, time: $startTime, attributes: $attributes);

        if ($span instanceof AddSpanResult) {
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

        $this->endSpan($span, time: $endTime, additionalAttributes: $additionalAttributes, includeMemoryUsage: $includeMemoryUsage);

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

    public function gracefullyEndSpans(bool $force = false): void
    {
        if ($this->isSampling() === false) {
            return;
        }

        $currentSpan = $this->currentSpan();

        while ($currentSpan !== null) {
            if ($currentSpan->end !== null) {
                break;
            }

            if ($this->gracefulSpanEnderClosure === null || $force || ($this->gracefulSpanEnderClosure)($currentSpan)) {
                $this->endSpan($currentSpan);
            }

            if ($currentSpan->parentSpanId === null) {
                break;
            }

            $currentSpan = $this->spans[$currentSpan->parentSpanId] ?? null;
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

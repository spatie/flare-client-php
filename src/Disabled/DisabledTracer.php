<?php

namespace Spatie\FlareClient\Disabled;

use Closure;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\SamplingType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tracer;

class DisabledTracer extends Tracer
{
    public function __construct()
    {
    }

    public function startTrace(
        ?string $traceparent = null,
        array $context = [],
        bool $forceSampling = false,
    ): SamplingType {
        return SamplingType::Off;
    }

    public function startTraceWithSpan(
        Span|Closure $span,
        array $context = [],
    ): ?Span {
        return null;
    }

    protected function initiateTrace(
        bool $sample,
        ?string $traceId = null,
        ?string $spanId = null,
    ): SamplingType {
        return SamplingType::Off;
    }

    public function isSampling(): bool
    {
        return false;
    }

    public function endTrace(): void
    {
    }

    public function currentTraceId(): ?string
    {
        return null;
    }

    public function traceParent(): string
    {
        return '';
    }

    /** @return array<Span> */
    public function currentTrace(): array
    {
        return [];
    }

    public function setCurrentSpanId(?string $id): void
    {
    }

    public function currentSpanId(): ?string
    {
        return null;
    }

    public function hasCurrentSpan(?FlareSpanType $spanType = null): bool
    {
        return false;
    }

    public function currentSpan(): ?Span
    {
        return null;
    }

    public function addRawSpan(Span $span): Span
    {
        return $this->emptySpan();
    }

    public function startSpan(
        string $name,
        ?int $time = null,
        array $attributes = [],
        bool $canStartTraces = true,
    ): ?Span {
        return null;
    }

    public function endSpan(
        ?Span $span = null,
        ?int $time = null,
        array|Closure $additionalAttributes = [],
    ): Span {
        return $this->emptySpan();
    }

    public function span(
        string $name,
        Closure $callback,
        array $attributes = [],
        ?Closure $endAttributes = null,
        bool $canStartTraces = true,
    ): null {
        return null;
    }

    /** @param array<string, mixed> $attributes */
    public function spanEvent(
        string $name,
        array $attributes = [],
        ?int $time = null,
    ): ?SpanEvent {
        return null;
    }

    public function trashCurrentTrace(
        SamplingType $samplingType = SamplingType::Waiting
    ): void {

    }

    public function configureSpansUsing(Closure $callback): static
    {
        return $this;
    }

    public function configureSpanEventsUsing(Closure $callback): static
    {
        return $this;
    }

    public function getTraces(): array
    {
        return [];
    }

    public function gracefullyHandleError(): void
    {
    }

    private function emptySpan(): Span
    {
        return new Span(
            'Flare Disabled',
            'Flare Disabled',
            null,
            'Flare Disabled',
            0,
            null,
            [],
        );
    }
}

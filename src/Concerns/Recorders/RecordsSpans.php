<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use InvalidArgumentException;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\TimeInterval;

/**
 * @template T of Span
 */
trait RecordsSpans
{
    /** @use RecordsEntries<T> */
    use RecordsEntries {
        RecordsEntries::persistEntry as protected basePersistEntry;
    }

    /** @var array<int, T> */
    protected array $stack = [];

    protected bool $shouldEndTrace = false;

    protected int $nestingCounter = 0;

    protected function shouldTrace(): bool
    {
        return $this->withTraces && $this->tracer->isSampling();
    }

    protected function shouldReport(): bool
    {
        return $this->withErrors;
    }

    protected function canStartTraces(): bool
    {
        return false;
    }

    protected function shouldStartTrace(Span $span): bool
    {
        return true;
    }

    /**
     * @param T $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $this->tracer->addRawSpan($entry);
    }

    /**
     * @param Closure():string|string|null $name
     * @param Closure():array<string, mixed>|array $attributes
     * @param Closure():array{name: string, attributes: array<string, mixed>}|null $nameAndAttributes
     * @param int|null $time
     * @param string|null $parentId
     *
     * @return Span|null
     */
    protected function startSpan(
        Closure|string|null $name = null,
        Closure|array $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $time = null,
        ?string $parentId = null,
    ): ?Span {
        if ($nameAndAttributes === null && $name === null) {
            throw new InvalidArgumentException('Either $nameAndAttributes must be set, or both $name and $attributes must be set.');
        }

        return $this->persistEntry(function () use ($nameAndAttributes, $parentId, $attributes, $time, $name) {
            $name = is_callable($name) ? $name() : $name;
            $attributes = is_callable($attributes) ? $attributes() : $attributes;

            if ($nameAndAttributes) {
                ['name' => $name, 'attributes' => $attributes] = $nameAndAttributes();
            }

            $span = new Span(
                traceId: $this->tracer->currentTraceId() ?? $this->tracer->ids->trace(),
                spanId: $this->tracer->ids->span(),
                parentSpanId: $parentId ?? $this->tracer->currentSpanId(),
                name: $name,
                start: $time ?? $this->tracer->time->getCurrentTime(),
                end: null,
                attributes: $attributes,
            );

            $this->stack[] = $span;
            $this->nestingCounter++;

            return $span;
        });
    }

    /**
     * @param Closure(T):(void|T|null)|null $spanCallback
     * @param Closure():array<string,mixed>|array<string,mixed> $additionalAttributes
     *
     * @return T|null
     */
    protected function endSpan(
        ?int $time = null,
        Closure|array $additionalAttributes = [],
        ?Closure $spanCallback = null,
    ): mixed {
        $shouldTrace = $this->withTraces && $this->tracer->isSampling();
        $shouldReport = $this->shouldReport();

        if ($shouldTrace === false && $shouldReport === false) {
            return null;
        }

        $span = array_pop($this->stack);

        if ($span === null) {
            return null;
        }

        if ($spanCallback !== null) {
            $spanCallback($span);
        }

        $this->tracer->endSpan($span, $time);

        if (is_callable($additionalAttributes)) {
            $additionalAttributes = $additionalAttributes();
        }

        if (count($additionalAttributes) > 0) {
            $span->addAttributes($additionalAttributes);
        }

        $this->tracer->setCurrentSpanId($span->parentSpanId);

        $this->nestingCounter--;

        if ($this->shouldEndTrace && $this->nestingCounter === 0) {
            $this->tracer->endTrace();
            $this->shouldEndTrace = false;
        }

        return $span;
    }

    /**
     * @param Closure():string|string|null $name
     * @param Closure():array<string, mixed>|array $attributes
     * @param Closure():array{name: string, attributes: array<string, mixed>}|null $nameAndAttributes
     * @param Closure():array<string,mixed>|null $additionalAttributes
     * @param Closure(T):(void|T|null)|null $spanCallback
     */
    protected function span(
        Closure|string|null $name = null,
        Closure|array $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
        ?string $parentId = null,
        ?Closure $additionalAttributes = null,
        ?Closure $spanCallback = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve(
            time: $this->tracer->time,
            start: $start,
            end: $end,
            duration: $duration,
        );

        $span = $this->startSpan(
            name: $name,
            attributes: $attributes,
            nameAndAttributes: $nameAndAttributes,
            time: $start,
            parentId: $parentId,
        );

        if ($span === null) {
            return null;
        }

        $this->endSpan(
            time: $end,
            additionalAttributes: $additionalAttributes === null ? [] : $additionalAttributes,
            spanCallback: $spanCallback,
        );

        $this->setOrigin($span);

        return $span;
    }

    protected function persistEntry(Closure $entry): ?Span
    {
        if (
            $this->withTraces === false
            || $this->tracer->isSampling()
            || $this->canStartTraces() === false
        ) {
            return $this->basePersistEntry($entry);
        }

        $span = $entry();

        if($this->shouldStartTrace($span) === false){
            return $this->basePersistEntry($span);
        }

        $spanInTrace = $this->tracer->startTraceWithSpan($span);

        if ($spanInTrace === null && $this->withErrors === false) {
            return null; // No sampling was started and not required for errors
        }

        if ($spanInTrace === null) {
            return $this->basePersistEntry($span); // No sampling was started, use it for errors
        }

        $this->shouldEndTrace = true;
        $this->nestingCounter = 1;

        return $this->basePersistEntry($span); // Sampling was started, use trace and span id from span
    }

    /** @return array<T> */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use InvalidArgumentException;
use Spatie\FlareClient\Spans\Span;

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

    /**
     * @param T $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $this->tracer->addSpan($entry, makeCurrent: true);
    }

    /**
     * @param Closure():string|string|null $name
     * @param Closure():array<string, mixed>|array $attributes
     * @param Closure():array{name: string, attributes: array<string, mixed>}|null $nameAndAttributes
     * @param int|null $start
     * @param string|null $parentId
     *
     * @return Span|null
     */
    protected function startSpan(
        Closure|string|null $name = null,
        Closure|array $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $start = null,
        ?string $parentId = null,
    ): ?Span {
        if ($nameAndAttributes === null && $name === null) {
            throw new InvalidArgumentException('Either $nameAndAttributes must be set, or both $name and $attributes must be set.');
        }

        return $this->persistEntry(function () use ($nameAndAttributes, $parentId, $attributes, $start, $name) {
            $name = is_callable($name) ? $name() : $name;
            $attributes = is_callable($attributes) ? $attributes() : $attributes;

            if ($nameAndAttributes) {
                ['name' => $name, 'attributes' => $attributes] = $nameAndAttributes();
            }

            $span = Span::build(
                traceId: $this->tracer->currentTraceId() ?? '', // In the case of a non trace but do collect spans for errors
                parentId: $parentId ?? $this->tracer->currentSpanId(),
                name: $name,
                start: $start,
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

    protected function span(
        Closure|string|null $name = null,
        Closure|array $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
        ?string $parentId = null,
        ?Closure $additionalAttributes = null,
    ): ?Span {
        [$start, $end] = match (true) {
            $start !== null && $end !== null => [$start, $end],
            $start !== null && $duration !== null => [$start, $start + $duration],
            $end !== null && $duration !== null => [$end, $end + $duration],
            $start === null && $end === null && $duration !== null => [$this->tracer->time()->getCurrentTime() - $duration, $this->tracer->time()->getCurrentTime()],
            default => throw new InvalidArgumentException('Span cannot be started, no valid timings provided'),
        };

        $span = $this->startSpan(
            name: $name,
            attributes: $attributes,
            nameAndAttributes: $nameAndAttributes,
            start: $start,
            parentId: $parentId,
        );

        if ($span === null) {
            return null;
        }

        $this->endSpan(
            time: $end,
            additionalAttributes: $additionalAttributes === null ? [] : $additionalAttributes,
        );

        $this->setOrigin($span);

        return $span;
    }

    protected function persistEntry(Closure $entry): ?Span
    {
        if ($this->withTraces === true
            && $this->tracer->isSampling() === false
            && $this->canStartTraces()
        ) {
            $this->potentiallyStartTrace();
        }

        return $this->basePersistEntry($entry);
    }

    protected function potentiallyStartTrace(): void
    {
        $this->tracer->potentialStartTrace();

        if ($this->tracer->isSampling()) {
            $this->shouldEndTrace = true;
        }
    }

    /** @return array<T> */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use InvalidArgumentException;
use Spatie\FlareClient\Spans\SpanEvent;

/**
 * @template T of SpanEvent
 */
trait RecordsSpanEvents
{
    /** @use RecordsEntries<T> */
    use RecordsEntries;

    protected function shouldTrace(): bool
    {
        return $this->withTraces
            && $this->tracer->isSampling()
            && $this->tracer->currentSpanId();
    }

    protected function shouldReport(): bool
    {
        return $this->withErrors;
    }

    /**
     * @param T $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $span = $this->tracer->currentSpan();

        if ($span === null) {
            return;
        }

        if (count($span->events) >= $this->tracer->limits->maxSpanEventsPerSpan) {
            $span->droppedEventsCount++;

            return;
        }

        $this->setOrigin($entry);

        $span->addEvent($entry);
    }

    /**
     * @param Closure():string|string|null $name
     * @param Closure():array<string, mixed>|array $attributes
     * @param Closure():array{name: string, attributes: array<string, mixed>}|null $nameAndAttributes
     * @param int|null $time
     * @param Closure(T):(void|T|null)|null $spanEventCallback
     *
     * @return SpanEvent|null
     */
    protected function spanEvent(
        string|Closure|null $name = null,
        array|Closure $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $time = null,
        ?Closure $spanEventCallback = null,
    ): ?SpanEvent {
        if ($nameAndAttributes === null && $name === null) {
            throw new InvalidArgumentException('Either $nameAndAttributes must be set, or both $name and $attributes must be set.');
        }

        return $this->persistEntry(function () use ($spanEventCallback, $attributes, $name, $time, $nameAndAttributes) {
            $name = is_callable($name) ? $name() : $name;
            $attributes = is_callable($attributes) ? $attributes() : $attributes;

            if ($nameAndAttributes) {
                ['name' => $name, 'attributes' => $attributes] = $nameAndAttributes();
            }

            $spanEvent = new SpanEvent(
                name: $name,
                timestamp: $time ?? $this->tracer->time->getCurrentTime(),
                attributes: $attributes,
            );

            if ($spanEventCallback) {
                $spanEventCallback($spanEvent);
            }

            return $spanEvent;
        });
    }

    /** @return array<T> */
    public function getSpanEvents(): array
    {
        return $this->entries;
    }
}

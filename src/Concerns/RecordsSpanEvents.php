<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Performance\Spans\SpanEvent;
use Spatie\FlareClient\Performance\Tracer;

/**
 * @template T of SpanEvent
 * @property Tracer $tracer
 */
trait RecordsSpanEvents
{
    /** @var array<T> */
    protected array $spanEvents = [];

    protected ?int $maxEntries = null;

    protected bool $traceSpanEvents = true;

    public function getSpanEvents(): array
    {
        return $this->spanEvents;
    }

    public function reset(): void
    {
        $this->spanEvents = [];
    }

    /**
     * @param T $spanEvent
     */
    protected function persistSpanEvent(mixed $spanEvent): void
    {
        if ($this->shouldTraceSpanEvents()) {
            $span = $this->tracer->currentSpan();

            $span->addEvent($spanEvent);
        }

        $this->spanEvents[] = $spanEvent;

        if ($this->maxEntries && count($this->spanEvents) > $this->maxEntries) {
            $this->removeOldestSpanEvent();
        }
    }


    protected function shouldTraceSpanEvents(): bool
    {
        return $this->traceSpanEvents
            && $this->tracer->isSamping()
            && $this->tracer->currentSpanId();
    }

    protected function removeOldestSpanEvent(): void
    {
        array_shift($this->spanEvents);
    }
}

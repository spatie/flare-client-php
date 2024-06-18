<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Performance\Spans\SpanEvent;
use Spatie\FlareClient\Performance\Tracer;
use SplObjectStorage;

/**
 * @template T of SpanEvent
 * @property Tracer $tracer
 */
trait RecordsSpanEvents
{
    /** @var SplObjectStorage<T, string> */
    protected SplObjectStorage $spanEvents;

    protected ?int $maxEntries = null;

    protected bool $traceSpanEvents = true;

    protected function initializeStorage(): void
    {
        $this->spanEvents = new SplObjectStorage();
    }

    public function getSpanEvents(): SplObjectStorage
    {
        return $this->spanEvents;
    }

    public function reset(): void
    {
        $this->spanEvents = new SplObjectStorage();
    }

    /**
     * @param T $spanEvent
     */
    protected function persistSpanEvent(mixed $spanEvent)
    {
        if ($this->shouldTraceSpanEvents()) {
            $span = $this->tracer->currentSpan();

            $span->addEvent($spanEvent);
            $this->spanEvents->attach($spanEvent, $span->spanId);
        } else {
            $this->spanEvents->attach($spanEvent, '');
        }

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
        $this->spanEvents->rewind();

        if (! $this->spanEvents->valid()) {
            return;
        }

        $spanEvent = $this->spanEvents->current();
        $spanId = $this->spanEvents->getInfo();

        $this->spanEvents->detach($spanEvent);

        if (! $this->shouldTraceSpanEvents()) {
            return;
        }

        $span = $this->tracer->traces[$this->tracer->currentTraceId()][$spanId] ?? null;

        if ($span) {
            $span->events->detach($spanEvent);
        }
    }
}

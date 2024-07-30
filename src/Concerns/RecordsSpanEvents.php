<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tracer;

/**
 * @template T of SpanEvent
 * @property Tracer $tracer
 */
trait RecordsSpanEvents
{
    /** @var array<T> */
    protected array $spanEvents = [];

    protected bool $traceSpanEvents = true;

    protected bool $reportSpanEvents = true;

    protected ?int $maxReportedSpanEvents = null;

    /** @return array<T> */
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
        if ($this->traceSpanEvents
            && $this->tracer->isSamping()
            && $this->tracer->currentSpanId()
        ) {
            $this->traceSpanEvent($spanEvent);
        }

        if ($this->reportSpanEvents === false) {
            return;
        }

        $this->spanEvents[] = $spanEvent;

        if ($this->maxReportedSpanEvents && count($this->spanEvents) > $this->maxReportedSpanEvents) {
            array_shift($this->spanEvents);
        }
    }

    /**
     * @param T $spanEvent
     */
    protected function traceSpanEvent(mixed $spanEvent): void
    {
        $span = $this->tracer->currentSpan();

        if (count($span->events) >= $this->tracer->limits->maxSpanEventsPerSpan) {
            $span->droppedEventsCount++;

            return;
        }

        $span->addEvent($spanEvent);
    }
}

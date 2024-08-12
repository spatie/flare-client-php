<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Spatie\FlareClient\Spans\SpanEvent;

/**
 * @template T of SpanEvent
 *
 * @uses  RecordsEntries<T>
 */
trait RecordsSpanEvents
{
    use RecordsEntries;

    protected function shouldTrace(): bool
    {
        return $this->trace
            && $this->tracer->isSamping()
            && $this->tracer->currentSpanId();
    }

    protected function shouldReport(): bool
    {
        return $this->report;
    }

    /**
     * @param SpanEvent $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $span = $this->tracer->currentSpan();

        if (count($span->events) >= $this->tracer->limits->maxSpanEventsPerSpan) {
            $span->droppedEventsCount++;

            return;
        }

        $this->setOrigin($entry);

        $span->addEvent($entry);
    }

    /** @return array<T> */
    public function getSpanEvents(): array
    {
        return $this->entries;
    }
}

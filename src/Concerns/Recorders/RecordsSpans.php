<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Spatie\FlareClient\Spans\Span;

/**
 * @template T of Span
 *
 * @uses  RecordsEntries<T>
 */
trait RecordsSpans
{
    use RecordsEntries;

    protected function shouldTrace(): bool
    {
        return $this->trace && $this->tracer->isSamping();
    }

    protected function shouldReport(): bool
    {
        return $this->report;
    }

    /**
     * @param Span $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $this->setOrigin($entry);

        $this->tracer->addSpan($entry);
    }

    /** @return Span */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

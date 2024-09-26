<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Spatie\FlareClient\Spans\Span;

/**
 * @template T of Span
 */
trait RecordsSpans
{
    /** @use RecordsEntries<T> */
    use RecordsEntries;

    protected function shouldTrace(): bool
    {
        return $this->trace && $this->tracer->isSampling();
    }

    protected function shouldReport(): bool
    {
        return $this->report;
    }

    /**
     * @param T $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $this->setOrigin($entry);

        $this->tracer->addSpan($entry);
    }

    /** @return array<T> */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

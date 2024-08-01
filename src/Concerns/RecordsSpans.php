<?php

namespace Spatie\FlareClient\Concerns;

use Closure;
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
     * @param T $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $this->tracer->addSpan($entry);
    }

    /** @return T[] */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use Spatie\FlareClient\Spans\Span;

/**
 * @template T of Span
 *
 * @uses  RecordsEntries<T>
 */
trait RecordsPendingSpans
{
    use RecordsEntries;

    /** @var array<int, T> */
    protected array $stack = [];

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
        $this->tracer->addSpan($entry, makeCurrent: true);
    }

    /**
     * @param Closure(): T|Span $entry
     *
     * @return Span
     */
    protected function startSpan(
        Closure|Span $span
    ): ?Span {
        return $this->persistEntry(function () use ($span) {
            if ($span instanceof Closure) {
                $span = $span();
            }

            $this->stack[] = $span;

            return $span;
        });
    }

    /**
     * @param Closure(T): void|null $closure
     *
     * @return Span|null
     */
    protected function endSpan(
        ?Closure $closure = null,
        ?int $time = null,
    ): ?Span {
        $shouldTrace = $this->shouldTrace();
        $shouldReport = $this->shouldReport();

        if ($shouldTrace === false && $shouldReport === false) {
            return null;
        }

        $span = array_pop($this->stack);

        if ($span === null) {
            return null;
        }

        if($closure !== null) {
            $closure($span);
        }

        $span->end($time);
        $this->tracer->setCurrentSpanId($span->parentSpanId);

        return $span;
    }

    /** @return Span */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

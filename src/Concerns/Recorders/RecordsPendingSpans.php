<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;

/**
 * @template T of Span
 *
 * @uses  RecordsEntries<T>
 */
trait RecordsPendingSpans
{
    use RecordsEntries {
        RecordsEntries::persistEntry as protected basePersistEntry;
    }

    /** @var array<int, T> */
    protected array $stack = [];

    protected bool $shouldEndTrace = false;

    protected int $nestingCounter = 0;

    protected function shouldTrace(): bool
    {
        return $this->trace && $this->tracer->isSampling();
    }


    protected function shouldReport(): bool
    {
        return $this->report;
    }

    protected function canStartTraces(): bool
    {
        return false;
    }

    /**
     * @param Span $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $this->tracer->addSpan($entry, makeCurrent: true);
    }

    /**
     * @param Closure(): T $entry
     *
     * @return T
     */
    protected function startSpan(
        Closure $span
    ): ?Span {
        return $this->persistEntry(function () use ($span) {
            if ($span instanceof Closure) {
                $span = $span();
            }

            $this->stack[] = $span;
            $this->nestingCounter++;

            return $span;
        });
    }

    protected function persistEntry(Closure $entry): ?Span
    {
        if ($this->trace === true
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

    /**
     * @param Closure(T): void|null $closure
     *
     * @return Span|T|null
     */
    protected function endSpan(
        ?Closure $closure = null,
        ?int $time = null,
        ?array $attributes = null
    ): ?Span {
        $shouldTrace = $this->trace && $this->tracer->isSampling();
        $shouldReport = $this->shouldReport();

        if ($shouldTrace === false && $shouldReport === false) {
            return null;
        }

        $span = array_pop($this->stack);

        if ($span === null) {
            return null;
        }

        if ($closure !== null) {
            $closure($span);
        }

        $span->end($time);

        if ($attributes !== null) {
            $span->addAttributes($attributes);
        }

        $this->tracer->setCurrentSpanId($span->parentSpanId);

        $this->nestingCounter--;

        if ($this->shouldEndTrace && $this->nestingCounter === 0) {
            $this->tracer->endTrace();
            $this->shouldEndTrace = false;
        }

        return $span;
    }

    /** @return Span */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

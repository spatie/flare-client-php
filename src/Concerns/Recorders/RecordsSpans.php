<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use Spatie\FlareClient\Spans\Span;

/**
 * @template T of Span
 */
trait RecordsSpans
{
    /** @use RecordsEntries<T> */
    use RecordsEntries {
        RecordsEntries::persistEntry as protected basePersistEntry;
    }

    /** @var array<int, T> */
    protected array $stack = [];

    protected bool $shouldEndTrace = false;

    protected int $nestingCounter = 0;

    protected function shouldTrace(): bool
    {
        return $this->withTraces && $this->tracer->isSampling();
    }

    protected function shouldReport(): bool
    {
        return $this->withErrors;
    }

    protected function canStartTraces(): bool
    {
        return false;
    }

    /**
     * @param T $entry
     */
    protected function traceEntry(mixed $entry): void
    {
        $this->tracer->addSpan($entry, makeCurrent: true);
    }

    /**
     * @param Closure():T $span
     *
     * @return T|null
     */
    protected function startSpan(
        Closure $span
    ) {
        /** @var ?T */
        $entry = $this->persistEntry(function () use ($span) {
            if ($span instanceof Closure) {
                $span = $span();
            }

            $this->stack[] = $span;
            $this->nestingCounter++;

            return $span;
        });

        return $entry;
    }

    protected function persistEntry(Closure $entry): ?Span
    {
        if ($this->withTraces === true
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
     * @param Closure(T):(void|T|null)|null $closure
     *
     * @return T|null
     */
    protected function endSpan(
        ?Closure $closure = null,
        ?int $time = null,
        ?array $attributes = null
    ): mixed {
        $shouldTrace = $this->withTraces && $this->tracer->isSampling();
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

        $this->tracer->endSpan($span, $time);

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

    /** @return array<T> */
    public function getSpans(): array
    {
        return $this->entries;
    }
}

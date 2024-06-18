<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Performance\Tracer;

/**
 * @template T of Span
 * @property Tracer $tracer
 */
trait RecordsSpans
{
    /** @var T[] */
    protected array $spans = [];

    protected ?int $maxEntries = null;

    protected bool $traceSpans = true;

    /**
     * @param T $span
     */
    protected function persistSpan(mixed $span): void
    {
        $this->spans[] = $span;

        if ($this->shouldTraceSpans()) {
            $this->tracer->addSpan($span);
        }

        if ($this->maxEntries && count($this->spans) > $this->maxEntries) {
            $this->removeOldestSpan();
        }
    }

    /** @return T[] */
    public function getSpans(): array
    {
        return $this->spans;
    }

    public function reset(): void
    {
        $this->spans = [];
    }

    protected function shouldTraceSpans(): bool
    {
        return $this->traceSpans && $this->tracer->isSamping();
    }

    protected function removeOldestSpan(): void
    {
        $span = array_shift($this->spans);

        if ($this->shouldTraceSpans()) {
            unset($this->tracer[$span->traceId][$span->spanId]);
        }
    }
}

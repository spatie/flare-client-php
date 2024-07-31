<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Tracer;

/**
 * @template T of Span
 * @property Tracer $tracer
 */
trait RecordsSpans
{
    /** @var T[] */
    protected array $spans = [];

    protected bool $traceSpans = true;

    protected bool $reportSpans = true;

    protected ?int $maxReportedSpans = null;

    /**
     * @param T $span
     */
    protected function persistSpan(mixed $span): void
    {
        if ($this->shouldTraceSpans()) {
            $this->tracer->addSpan($span);
        }

        if($this->reportSpans === false) {
            return;
        }

        $this->spans[] = $span;

        if ($this->maxReportedSpans && count($this->spans) > $this->maxReportedSpans) {
            array_shift($this->spans);
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
}

<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;

/**
 * @template T of Span|SpanEvent
 */
trait BacktracingRecorder
{
    protected bool $findOrigin = false;

    protected ?int $findOriginThreshold = null;

    protected BackTracer $backTracer;

    private function configureBacktracing(array $config): void
    {
        $this->findOrigin = $config['find_origin'] ?? false;
        $this->findOriginThreshold = $config['find_origin_threshold'] ?? null;
    }

    /**
     * @param T $entry
     *
     * @return T
     */
    final protected function backtraceEntry(
        mixed $entry,
        ?Closure $frameAfter = null
    ): mixed {
        $duration = match (true) {
            $entry instanceof Span => $entry->end - $entry->start,
            $entry instanceof SpanEvent => null,
        };

        $shouldBacktrace =  $this->findOrigin
            && ($this->findOriginThreshold === null || $duration >= $this->findOriginThreshold);

        if($shouldBacktrace === false){
            return $entry;
        }

        $frame = $frameAfter
            ? $this->backTracer->after($frameAfter, 20)
            : $this->backTracer->firstApplicationFrame(20);

        $entry->addAttributes($this->backTracer->frameToAttributes($frame));

        return $entry;
    }
}

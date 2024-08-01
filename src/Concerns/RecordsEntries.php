<?php

namespace Spatie\FlareClient\Concerns;

use Closure;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tracer;

/**
 * @template T of SpanEvent|Span
 */
trait RecordsEntries
{
    /** @var array<T> */
    protected array $entries = [];

    protected bool $trace = false;

    protected bool $report = false;

    protected ?int $maxReported = null;

    abstract protected function shouldTrace(): bool;

    abstract protected function shouldReport(): bool;

    /**
     * @param T $entry
     */
    abstract protected function traceEntry(mixed $entry): void;

    public function __construct(
        protected Tracer $tracer,
        ?array $config = null,
    ) {
        if($config){
            $this->configure($config);
        }
    }

    public function start(): void
    {
        // Most of the time only required in framework specific implementations
    }

    public function reset(): void
    {
        $this->entries = [];
    }

    public function configure(array $config): void
    {
        $this->configureRecorder($config);
    }

    /**
     * @param Closure(): T|T $entry
     *
     * @return T
     */
    protected function persistEntry(Closure|SpanEvent|Span $entry): null|Span|SpanEvent
    {
        $shouldTrace = $this->shouldTrace();
        $shouldReport = $this->shouldReport();

        if ($shouldTrace === false && $shouldReport === false) {
            return null;
        }

        if ($entry instanceof Closure) {
            $entry = $entry();
        }

        if ($shouldTrace) {
            $this->traceEntry($entry);
        }

        if ($shouldReport  === false) {
            return $entry;
        }

        $this->entries[] = $entry;

        if ($this->maxReported && count($this->entries) > $this->maxReported) {
            array_shift($this->entries);
        }

        return $entry;
    }

    protected function configureRecorder(array $config): void
    {
        $this->trace = $config['trace'] ?? false;
        $this->report = $config['report'] ?? false;
        $this->maxReported = $config['max_reported'] ?? null;
    }
}

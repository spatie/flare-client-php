<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;
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

    protected bool $findOrigin = false;

    protected ?int $findOriginThreshold = null;

    abstract protected function shouldTrace(): bool;

    abstract protected function shouldReport(): bool;

    /**
     * @param SpanEvent $entry
     */
    abstract protected function traceEntry(mixed $entry): void;

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $config
        );
    }

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        array $config,
    ) {
        $this->configure($config);
    }

    public function start(): void
    {
        // Most of the time only required in framework specific implementations
    }

    public function reset(): void
    {
        $this->entries = [];
    }

    protected function configure(array $config): void
    {
        $this->configureRecorder($config);
    }

    protected function configureRecorder(array $config): void
    {
        $this->trace = $config['trace'] ?? false;
        $this->report = $config['report'] ?? false;
        $this->maxReported = $config['max_reported'] ?? null;

        $this->findOrigin = $config['find_origin'] ?? false;
        $this->findOriginThreshold = $config['find_origin_threshold'] ?? null;
    }

    /**
     * @param Closure(): T $entry
     *
     * @return ?T
     */
    protected function persistEntry(Closure $entry): null|Span|SpanEvent
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

        if ($shouldReport === false) {
            return $entry;
        }

        $this->entries[] = $entry;

        if ($this->maxReported && count($this->entries) > $this->maxReported) {
            array_shift($this->entries);
        }

        return $entry;
    }

    /**
     * @template T of Span|SpanEvent
     *
     * @param T $entry
     *
     * @return T
     */
    protected function setOrigin(
        Span|SpanEvent $entry,
        ?Closure $frameAfter = null
    ): Span|SpanEvent {
        $duration = match (true) {
            $entry instanceof Span => $entry->end - $entry->start,
            $entry instanceof SpanEvent => null,
        };

        if (! $this->shouldFindOrigin($duration)) {
            return $entry;
        }

        $frame = $frameAfter
            ? $this->backTracer->after($frameAfter, 20)
            : $this->backTracer->firstApplicationFrame(20);

        if ($frame === null) {
            return $entry;
        }

        $function = match (true) {
            $frame->class && $frame->method => "{$frame->class}::{$frame->method}",
            $frame->method => $frame->method,
            $frame->class => $frame->class,
            default => 'unknown',
        };

        $entry->addAttributes([
            'code.filepath' => $frame->file,
            'code.lineno' => $frame->lineNumber,
            'code.function' => $function,
        ]);

        return $entry;
    }


    protected function shouldFindOrigin(?int $duration): bool
    {
        return $this->shouldTrace()
            && $this->findOrigin
            && ($this->findOriginThreshold === null || $duration >= $this->findOriginThreshold);
    }
}

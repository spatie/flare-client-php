<?php

namespace Spatie\FlareClient\Recorders;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\Recorders\BacktracingRecorder;
use Spatie\FlareClient\Concerns\Recorders\ErrorsRecorder;
use Spatie\FlareClient\Concerns\Recorders\TracingRecorder;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder as SpanEventsRecorderContract;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;

abstract class SpanEventsRecorder extends Recorder implements SpanEventsRecorderContract
{
    /** @use BacktracingRecorder<SpanEvent> */
    use BacktracingRecorder;

    /** @use ErrorsRecorder<SpanEvent> */
    use ErrorsRecorder;

    use TracingRecorder;

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new static(
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
        $this->configureRecorder($config);
    }

    private function configureRecorder(array $config): void
    {
        if (array_key_exists('find_origin_threshold', $config)) {
            unset($config['find_origin_threshold']);
        }

        $this->configureErrorsRecording($config);
        $this->configureBacktracing($config);
        $this->configureTracing($config);
        $this->configure($config);
    }

    protected function configure(array $config): void
    {
        // Most of the time only required in framework specific implementations
    }

    public function boot(): void
    {
        // Most of the time only required in framework specific implementations
    }

    private function addEntryToTrace(SpanEvent $entry): void
    {
        $span = $this->tracer->currentSpan();

        if ($span === null) {
            return;
        }

        if (count($span->events) >= $this->tracer->limits->maxSpanEventsPerSpan) {
            $span->droppedEventsCount++;

            return;
        }

        $this->backtraceEntry($entry);

        $span->addEvent($entry);
    }

    /**
     * @param Closure():string|string|null $name
     * @param Closure():array<string, mixed>|array $attributes
     * @param Closure():array{name: string, attributes: array<string, mixed>}|null $nameAndAttributes
     * @param int|null $time
     * @param Closure(SpanEvent):(void|SpanEvent|null)|null $spanEventCallback
     *
     * @return SpanEvent|null
     */
    final protected function spanEvent(
        string|Closure|null $name = null,
        array|Closure $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $time = null,
        ?Closure $spanEventCallback = null,
    ): ?SpanEvent {
        if ($nameAndAttributes === null && $name === null) {
            throw new InvalidArgumentException('Either $nameAndAttributes must be set, or both $name and $attributes must be set.');
        }

        $shouldReport = $this->shouldReport();
        $shouldTrace = $this->withTraces && $this->tracer->isSampling() && $this->tracer->currentSpanId();

        if ($shouldReport === false && $shouldTrace === false) {
            return null;
        }

        $name = is_callable($name) ? $name() : $name;
        $attributes = is_callable($attributes) ? $attributes() : $attributes;

        if ($nameAndAttributes) {
            ['name' => $name, 'attributes' => $attributes] = $nameAndAttributes();
        }

        $spanEvent = new SpanEvent(
            name: $name,
            timestamp: $time ?? $this->tracer->time->getCurrentTime(),
            attributes: $attributes,
        );

        if ($spanEventCallback) {
            $spanEventCallback($spanEvent);
        }

        if ($shouldReport) {
            $this->addEntryToReport($spanEvent);
        }

        if ($shouldTrace) {
            $this->addEntryToTrace($spanEvent);
        }

        return $spanEvent;
    }

    final public function getSpanEvents(): array
    {
        return $this->entries;
    }
}

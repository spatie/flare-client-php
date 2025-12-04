<?php

namespace Spatie\FlareClient\Recorders;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\Recorders\BacktracingRecorder;
use Spatie\FlareClient\Concerns\Recorders\ErrorsRecorder;
use Spatie\FlareClient\Concerns\Recorders\TracingRecorder;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder as SpansRecorderContract;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\TimeInterval;
use Spatie\FlareClient\Tracer;

abstract class SpansRecorder extends Recorder implements SpansRecorderContract
{
    /** @use BacktracingRecorder<Span> */
    use BacktracingRecorder;

    /** @use ErrorsRecorder<Span> */
    use ErrorsRecorder;
    use TracingRecorder;

    /** @var array<Span> */
    private array $stack = [];

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
        $this->configureErrorsRecording($config);
        $this->configureBacktracing($config);
        $this->configureTracing($config);
        $this->configure($config);
    }

    protected function configure(array $config): void
    {
        // Meant to be overridden in the implementing recorder
    }

    public function boot(): void
    {
        // Most of the time only required in framework specific implementations
    }

    /**
     * @param Closure():string|string|null $name
     * @param Closure():array<string, mixed>|array $attributes
     * @param Closure():array{name: string, attributes: array<string, mixed>}|null $nameAndAttributes
     * @param int|null $time
     *
     * @return Span|null
     */
    final protected function startSpan(
        Closure|string|null $name = null,
        Closure|array $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $time = null,
    ): ?Span {
        if ($nameAndAttributes === null && $name === null) {
            throw new InvalidArgumentException('Either $nameAndAttributes must be set, or both $name and $attributes must be set.');
        }

        $shouldTrace = $this->withTraces && $this->tracer->sampling;
        $shouldReport = $this->shouldReport();

        if ($shouldTrace === false && $shouldReport === false) {
            return null;
        }

        $name = is_callable($name) ? $name() : $name;
        $attributes = is_callable($attributes) ? $attributes() : $attributes;

        if ($nameAndAttributes) {
            ['name' => $name, 'attributes' => $attributes] = $nameAndAttributes();
        }

        // Order of operations is important here, do not inline!
        $parentSpanId = $this->tracer->currentParentSpanId();
        $spanId = $this->tracer->nextSpanId();

        $span = new Span(
            traceId: $this->tracer->currentTraceId(),
            spanId: $spanId,
            parentSpanId: $parentSpanId,
            name: $name,
            start: $time ?? $this->tracer->time->getCurrentTime(),
            end: null,
            attributes: $attributes,
        );

        $this->stack[] = $span;

        if ($shouldReport) {
            $this->addEntryToReport($span);
        }

        if ($shouldTrace) {
            $this->tracer->addSpan($span);
        }

        return $span;
    }


    /**
     * @param Closure(Span):(void|Span|null)|null $spanCallback
     * @param Closure():array<string,mixed>|array<string,mixed> $additionalAttributes
     */
    final protected function endSpan(
        ?int $time = null,
        Closure|array $additionalAttributes = [],
        ?Closure $spanCallback = null,
    ): ?Span {
        $span = array_pop($this->stack);

        if ($span === null) {
            return null;
        }

        $span->end = $time ?? $this->tracer->time->getCurrentTime();

        if ($spanCallback) {
            $spanCallback($span);
        }

        if (is_callable($additionalAttributes)) {
            $additionalAttributes = $additionalAttributes();
        }

        if (count($additionalAttributes) > 0) {
            $span->addAttributes($additionalAttributes);
        }

        if ($this->withTraces === false
            || $this->tracer->sampling === false
            || $this->tracer->disabled === true) {
            return $span;
        }

        $this->tracer->endSpan($span);

        return $span;
    }


    /**
     * @param Closure():string|string|null $name
     * @param Closure():array<string, mixed>|array $attributes
     * @param Closure():array{name: string, attributes: array<string, mixed>}|null $nameAndAttributes
     * @param Closure():array<string,mixed>|null $additionalAttributes
     * @param Closure(Span):(void|Span|null)|null $spanCallback
     */
    final protected function span(
        Closure|string|null $name = null,
        Closure|array $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
        ?Closure $additionalAttributes = null,
        ?Closure $spanCallback = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve(
            time: $this->tracer->time,
            start: $start,
            end: $end,
            duration: $duration,
        );

        $span = $this->startSpan(
            name: $name,
            attributes: $attributes,
            nameAndAttributes: $nameAndAttributes,
            time: $start,
        );

        if ($span === null) {
            return null;
        }

        $this->endSpan(
            time: $end,
            additionalAttributes: $additionalAttributes === null ? [] : $additionalAttributes,
            spanCallback: $spanCallback,
        );


        if ($this->withTraces && $this->tracer->sampling === true) {
            $this->backtraceEntry($span);
        }

        return $span;
    }

    final public function getSpans(): array
    {
        return $this->entries;
    }
}

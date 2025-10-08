<?php

namespace Spatie\FlareClient\Recorders;

use Closure;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\Recorders\BacktracingRecorder;
use Spatie\FlareClient\Concerns\Recorders\ErrorsRecorder;
use Spatie\FlareClient\Concerns\Recorders\TracingRecorder;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder as SpansRecorderContract;
use Spatie\FlareClient\Enums\SamplingType;
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

    private int $nestingCounter = 0;

    /** @var array<Span> */
    private array $stack = [];

    private bool $startedTrace = false;

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
     * @param string|null $parentId
     *
     * @return Span|null
     */
    final protected function startSpan(
        Closure|string|null $name = null,
        Closure|array $attributes = [],
        ?Closure $nameAndAttributes = null,
        ?int $time = null,
        ?string $parentId = null,
        bool $canStartTrace = false,
    ): ?Span {
        if ($nameAndAttributes === null && $name === null) {
            throw new InvalidArgumentException('Either $nameAndAttributes must be set, or both $name and $attributes must be set.');
        }

        $startedTraceForSpan = $this->potentiallyStartTrace($canStartTrace);

        $shouldTrace = $this->withTraces && $this->tracer->samplingType === SamplingType::Sampling;
        $shouldReport = $this->shouldReport();

        // In the case of an unsampled trace we still want to end the trace after the outermost span, so raise already here
        $this->nestingCounter++;

        if ($shouldTrace === false && $shouldReport === false) {
            return null;
        }

        $name = is_callable($name) ? $name() : $name;
        $attributes = is_callable($attributes) ? $attributes() : $attributes;

        if ($nameAndAttributes) {
            ['name' => $name, 'attributes' => $attributes] = $nameAndAttributes();
        }

        $spanId = $startedTraceForSpan && $this->tracer->currentSpanId()
            ? $this->tracer->currentSpanId()
            : $this->tracer->ids->span();

        $parentSpanId = $startedTraceForSpan
            ? null
            : ($parentId ?? $this->tracer->currentSpanId());

        $span = new Span(
            traceId: $this->tracer->currentTraceId() ?? $this->tracer->ids->trace(),
            spanId: $spanId,
            parentSpanId: $parentSpanId,
            name: $name,
            start: $time ?? $this->tracer->time->getCurrentTime(),
            end: null,
            attributes: $attributes,
        );

        if ($shouldReport) {
            $this->addEntryToReport($span);
        }

        $this->stack[] = $span;

        if ($shouldTrace) {
            $this->tracer->addRawSpan($span);
        }

        return $span;
    }

    private function potentiallyStartTrace(bool $canStartTrace): bool
    {
        $shouldStartTrace = $canStartTrace
            && $this->withTraces === true
            && $this->tracer->samplingType === SamplingType::Waiting
            && $this->nestingCounter === 0;

        if (! $shouldStartTrace) {
            return false;
        }

        $this->tracer->startTrace();
        $this->startedTrace = true;

        return true;
    }

    final protected function potentiallyResumeTrace(
        ?string $traceParent,
    ): bool {
        $shouldStartTrace = $traceParent !== null
            && $this->withTraces === true
            && $this->tracer->samplingType === SamplingType::Waiting
            && $this->nestingCounter === 0;

        if (! $shouldStartTrace) {
            return false;
        }

        $this->tracer->startTrace($traceParent);
        $this->startedTrace = true;

        return true;
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
        $this->nestingCounter--;

        $span = array_pop($this->stack);

        $alreadyModifiedSpan = false;

        if ($this->withErrors && $span !== null) {
            $this->modifySpanToEnd($span, $time, $additionalAttributes, $spanCallback);

            $alreadyModifiedSpan = true;
        }

        if ($this->withTraces === false
            || $this->tracer->samplingType === SamplingType::Disabled
            || $this->tracer->samplingType === SamplingType::Waiting
        ) {
            return null;
        }

        if ($this->startedTrace
            && $this->tracer->samplingType === SamplingType::Off
            && $this->nestingCounter === 0
        ) {
            $this->startedTrace = false;
            $this->tracer->endTrace();

            return $span;
        }

        if ($this->tracer->samplingType === SamplingType::Off) {
            return $span;
        }

        if ($span === null) {
            // We should not end up here

            return null;
        }

        if ($alreadyModifiedSpan === false) {
            $this->modifySpanToEnd($span, $time, $additionalAttributes, $spanCallback);
        }

        $this->tracer->endSpan($span);

        if ($this->startedTrace && $this->nestingCounter === 0) {
            $this->startedTrace = false;
            $this->tracer->endTrace();
        }

        return $span;
    }

    private function modifySpanToEnd(
        Span $span,
        ?int $time,
        Closure|array $additionalAttributes,
        ?Closure $spanCallback,
    ): Span {
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
        ?string $parentId = null,
        ?Closure $additionalAttributes = null,
        ?Closure $spanCallback = null,
        bool $canStartTrace = false,
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
            parentId: $parentId,
            canStartTrace: $canStartTrace
        );

        if ($span === null) {
            return null;
        }

        $this->endSpan(
            time: $end,
            additionalAttributes: $additionalAttributes === null ? [] : $additionalAttributes,
            spanCallback: $spanCallback,
        );

        if ($this->withTraces && $this->tracer->samplingType === SamplingType::Sampling) {
            $this->backtraceEntry($span);
        }

        return $span;
    }

    final public function getSpans(): array
    {
        return $this->entries;
    }
}

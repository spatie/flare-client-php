<?php

namespace Spatie\FlareClient\Recorders\ApplicationRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\TimeInterval;

class ApplicationRecorder implements Recorder
{
    /** @use  RecordsSpans<Span> */
    use RecordsSpans;

    protected bool $running = false;

    protected bool $registering = false;

    protected bool $booting = false;

    protected bool $terminating = false;

    protected function configure(array $config): void
    {
        $this->withTraces = true;
    }

    protected function canStartTraces(): bool
    {
        return true;
    }

    protected function shouldStartTrace(Span $span): bool
    {
        return ($span->attributes['flare.span_type'] ?? null) === SpanType::Application;
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Application;
    }

    public function recordStart(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->running) {
            return null;
        }

        $serviceName = $this->tracer->resource->attributes['service.name'] ?? null;

        $this->running = true;

        return $this->startSpan(
            name: $serviceName ? "App - {$serviceName}" : 'App',
            attributes: [
                'flare.span_type' => SpanType::Application,
                ...$attributes,
            ],
            time: $time,
        );
    }

    public function recordRegistering(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->running === false || $this->registering) {
            return null;
        }

        $this->registering = true;

        return $this->startSpan(
            name: "Registering App",
            attributes: [
                'flare.span_type' => SpanType::ApplicationRegistration,
                ...$attributes,
            ],
            time: $time,
        );
    }

    public function recordRegistered(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->running === false || $this->registering === false) {
            return null;
        }

        $this->registering = false;

        return $this->endSpan(time: $time, additionalAttributes: $attributes);
    }

    public function recordRegistration(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordRegistering($attributes, time: $start)) {
            return $this->recordRegistered(time: $end);
        }

        return null;
    }

    public function recordBooting(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->running === false || $this->booting) {
            return null;
        }

        if ($this->registering) {
            $this->recordRegistered(time: $time);
        }

        $this->booting = true;

        return $this->startSpan(
            name: "Booting App",
            attributes: [
                'flare.span_type' => SpanType::ApplicationBoot,
                ...$attributes,
            ],
            time: $time,
        );
    }

    public function recordBooted(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->running === false || $this->booting === false) {
            return null;
        }

        $this->booting = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes
        );
    }

    public function recordBoot(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordBooting($attributes, time: $start)) {
            return $this->recordBooted(time: $end);
        }

        return null;
    }

    public function recordTerminating(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->running === false || $this->terminating) {
            return null;
        }

        if ($this->booting) {
            $this->recordBooted(time: $time);
        }

        $this->terminating = true;

        return $this->startSpan(
            name: "Terminating App",
            attributes: [
                'flare.span_type' => SpanType::ApplicationTerminating,
                ...$attributes,
            ],
            time: $time,
        );
    }

    public function recordTerminated(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->running === false || $this->terminating === false) {
            return null;
        }

        $this->terminating = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes
        );
    }

    public function recordTermination(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordTerminating($attributes, time: $start)) {
            return $this->recordTerminated(time: $end);
        }

        return null;
    }

    public function recordEnd(
        array $attributes = [],
        ?int $time = null,
    ): ?Span {
        if ($this->running === false) {
            return null;
        }

        if ($this->registering) {
            $this->recordRegistered(time:  $time);
        }

        if ($this->booting) {
            $this->recordBooted(time:  $time);
        }

        if ($this->terminating) {
            $this->recordTerminated(time:  $time);
        }

        $this->running = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes
        );
    }
}

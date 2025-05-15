<?php

namespace Spatie\FlareClient\Recorders\RoutingRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\TimeInterval;

class RoutingRecorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    protected bool $beforeMiddleware = false;

    protected bool $globalBeforeMiddleware = false;

    protected bool $routing = false;

    protected bool $afterMiddleware = false;

    protected bool $globalAfterMiddleware = false;

    protected function configure(array $config): void
    {
        $this->withTraces = true;
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Routing;
    }

    public function recordGlobalBeforeMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if($this->globalBeforeMiddleware === true) {
            return null;
        }

        $this->globalBeforeMiddleware = true;

        return $this->startSpan(
            'Global Middleware (before)',
            attributes: [
                'flare.span_type' => SpanType::GlobalBeforeMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordGlobalBeforeMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->globalBeforeMiddleware === false) {
            return null;
        }

        $this->globalAfterMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordGlobalBeforeMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordGlobalBeforeMiddlewareStart($attributes, time: $start)) {
            return $this->recordGlobalBeforeMiddlewareEnd(time: $end);
        }

        return null;
    }

    public function recordBeforeMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if($this->beforeMiddleware === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        $this->beforeMiddleware = true;

        return $this->startSpan(
            'Middleware (before)',
            attributes: [
                'flare.span_type' => SpanType::BeforeMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordBeforeMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->beforeMiddleware === false) {
            return null;
        }

        $this->beforeMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordBeforeMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordBeforeMiddlewareStart($attributes, time: $start)) {
            return $this->recordBeforeMiddlewareEnd(time: $end);
        }

        return null;
    }

    public function recordRoutingStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if($this->routing === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        if($this->beforeMiddleware) {
            $this->recordBeforeMiddlewareEnd(time: $time);
        }

        return $this->startSpan(
            'Routing',
            attributes: [
                'flare.span_type' => SpanType::Routing,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordRoutingEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordRouting(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordRoutingStart($attributes, time: $start)) {
            return $this->recordRoutingEnd(time: $end);
        }

        return null;
    }

    public function recordAfterMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if($this->afterMiddleware === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        if ($this->beforeMiddleware) {
            $this->recordBeforeMiddlewareEnd(time: $time);
        }

        $this->afterMiddleware = true;

        return $this->startSpan(
            'Middleware (after)',
            attributes: [
                'flare.span_type' => SpanType::AfterMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordAfterMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->afterMiddleware === false) {
            return null;
        }

        $this->afterMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordAfterMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordAfterMiddlewareStart($attributes, time: $start)) {
            return $this->recordAfterMiddlewareEnd(time: $end);
        }

        return null;
    }

    public function recordGlobalAfterMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if($this->globalAfterMiddleware === true) {
            return null;
        }

        if ($this->globalBeforeMiddleware) {
            $this->recordGlobalBeforeMiddlewareEnd(time: $time);
        }

        if ($this->beforeMiddleware) {
            $this->recordBeforeMiddlewareEnd(time: $time);
        }

        if($this->afterMiddleware) {
            $this->recordAfterMiddlewareEnd(time: $time);
        }

        $this->globalAfterMiddleware = true;

        return $this->startSpan(
            'Global Middleware (after)',
            attributes: [
                'flare.span_type' => SpanType::GlobalAfterMiddleware,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordGlobalAfterMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        if ($this->globalAfterMiddleware === false) {
            return null;
        }

        $this->globalAfterMiddleware = false;

        return $this->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordGlobalAfterMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span {
        [$start, $end] = TimeInterval::resolve($this->tracer->time, $start, $end, $duration);

        if ($this->recordGlobalAfterMiddlewareStart($attributes, time: $start)) {
            return $this->recordGlobalAfterMiddlewareEnd(time: $end);
        }

        return null;
    }
}

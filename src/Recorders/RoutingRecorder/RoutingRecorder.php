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
    use RecordsSpans;

    protected bool $globalMiddlewareBefore = false;

    protected $middlewareBefore = false;

    protected bool $globalMiddlewareAfter = false;

    public static function type(): string|RecorderType
    {
        return RecorderType::Routing;
    }

    public function recordGlobalBeforeMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        return $this->startSpan(
            'Global Middleware (before)',
            attributes: [
                'flare.span_type' => SpanType::GlobalMiddlewareBefore,
                ...$attributes,
            ],
            time: $time
        );
    }

    public function recordGlovalBeforeMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
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
        return $this->span(
            'Global Middleware (before)',
            attributes: [
                'flare.span_type' => SpanType::GlobalMiddlewareBefore,
                ...$attributes,
            ],
            start: $start,
            end: $end,
            duration: $duration
        );
    }

    public function recordBeforeMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
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
        return $this->span(
            'Middleware (before)',
            attributes: [
                'flare.span_type' => SpanType::BeforeMiddleware,
                ...$attributes,
            ],
            start: $start,
            end: $end,
            duration: $duration
        );
    }

    public function recordAfterMiddlewareStart(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        return $this->tracer->startSpan(
            'Middleware (after)',
            time: $time,
            attributes: [
                'flare.span_type' => SpanType::AfterMiddleware,
                ...$attributes,
            ]
        );
    }

    public function recordAfterMiddlewareEnd(
        array $attributes = [],
        ?int $time = null
    ): ?Span {
        return $this->tracer->endSpan(
            time: $time,
            additionalAttributes: $attributes,
        );
    }

    public function recordAfterMiddleware(
        array $attributes = [],
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): ?Span
    {
        return $this->span(
            'Middleware (after)',
            attributes: [
                'flare.span_type' => SpanType::AfterMiddleware,
                ...$attributes,
            ],
            start: $start,
            end: $end,
            duration: $duration
        );
    }
}

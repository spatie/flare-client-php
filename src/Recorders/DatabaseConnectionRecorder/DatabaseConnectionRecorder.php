<?php

namespace Spatie\FlareClient\Recorders\DatabaseConnectionRecorder;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;

class DatabaseConnectionRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::DatabaseConnection;
    }

    public function recordStart(
        ?string $databaseName = null,
        ?string $driverName = null,
        array $attributes = [],
    ): ?Span {
        return $this->startSpan(
            name: 'Database connection',
            attributes: fn () => array_filter([
                'flare.span_type' => SpanType::DatabaseConnection,
                'db.system.name' => $driverName,
                'db.namespace' => $databaseName,
                ...$attributes,
            ], fn (mixed $value) => $value !== null),
        );
    }

    public function recordEnd(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes: $attributes);
    }

    public function recordFailed(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(
            additionalAttributes: $attributes,
            spanCallback: fn (Span $span) => $span->setStatus(SpanStatusCode::Error),
        );
    }
}

<?php

namespace Spatie\FlareClient\Recorders\QueryRecorder;

use Closure;
use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\Span;

class QueryRecorder extends Recorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    protected bool $includeBindings = true;

    protected bool $findOrigin = false;

    protected ?int $findOriginThreshold = null;

    public const DEFAULT_INCLUDE_BINDINGS = true;

    public const DEFAULT_FIND_ORIGIN = true;

    public const DEFAULT_FIND_ORIGIN_THRESHOLD = 300_000;

    public static function type(): string|RecorderType
    {
        return RecorderType::Query;
    }

    protected function configure(array $config): void
    {
        $this->configureRecorder($config);

        $this->includeBindings = $config['include_bindings'] ?? true;
    }

    public function record(
        string $sql,
        int $duration,
        ?array $bindings = null,
        ?string $databaseName = null,
        ?string $driverName = null,
        array $attributes = [],
    ): ?Span {
        return $this->span(
            name: $this->resolveName($sql),
            attributes: $this->resolveAttributesClosure($sql, $bindings, $databaseName, $driverName, $attributes),
            duration: $duration,
        );
    }

    public function recordStart(
        string $sql,
        ?array $bindings = null,
        ?string $databaseName = null,
        ?string $driverName = null,
        array $attributes = [],
    ): ?Span {
        return $this->startSpan(
            name: "Query - {$sql}",
            attributes: $this->resolveAttributesClosure($sql, $bindings, $databaseName, $driverName, $attributes),
        );
    }

    public function recordEnd(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes: $attributes);
    }

    protected function resolveName(
        string $sql,
    ): string {
        return "Query - {$sql}";
    }

    protected function resolveAttributesClosure(
        string $sql,
        ?array $bindings,
        ?string $databaseName,
        ?string $driverName,
        array $attributes,
    ): Closure {
        return function () use ($attributes, $driverName, $databaseName, $bindings, $sql) {
            $attributes = [
                'flare.span_type' => SpanType::Query,
                'db.system' => $driverName,
                'db.name' => $databaseName,
                'db.statement' => $sql,
                'db.sql.bindings' => $bindings,
                ...$attributes,
            ];

            if (! $this->includeBindings) {
                unset($attributes['db.sql.bindings']);
            }

            return $attributes;
        };
    }
}

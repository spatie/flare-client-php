<?php

namespace Spatie\FlareClient\Recorders\QueryRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Concerns\UsesTime;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\Span;

class QueryRecorder extends Recorder implements SpansRecorder
{
    use UsesTime;

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
        $span = $this->recordStart(
            sql: $sql,
            bindings: $bindings,
            databaseName: $databaseName,
            driverName: $driverName,
            start: self::getCurrentTime() - $duration,
            attributes: $attributes,
        );

        if ($span === null) {
            return null;
        }

        $this->recordEnd();

        $this->setOrigin($span);

        return $span;
    }

    public function recordStart(
        string $sql,
        ?array $bindings = null,
        ?string $databaseName = null,
        ?string $driverName = null,
        ?int $start = null,
        array $attributes = [],
    ): ?Span {
        return $this->startSpan(function () use ($start, $attributes, $driverName, $databaseName, $bindings, $sql) {
            $attributes = [
                'flare.span_type' => SpanType::Query,
                'db.system' => $driverName,
                'db.name' => $databaseName,
                'db.statement' => $sql,
                'db.sql.bindings' => $bindings,
                ... $attributes,
            ];

            if (! $this->includeBindings) {
                unset($attributes['db.sql.bindings']);
            }

            return Span::build(
                traceId: $this->tracer->currentTraceId() ?? '',
                parentId: $this->tracer->currentSpan()?->spanId,
                name: "Query - {$sql}",
                start: $start,
                attributes: $attributes,
            );
        });
    }

    public function recordEnd(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(
            attributes: $attributes,
        );
    }
}

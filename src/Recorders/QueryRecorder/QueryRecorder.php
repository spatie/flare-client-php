<?php

namespace Spatie\FlareClient\Recorders\QueryRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;

class QueryRecorder  extends Recorder  implements SpansRecorder
{
    /** @use RecordsSpans<QuerySpan> */
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
        FlareSpanType $spanType = SpanType::Query,
        ?int $end = null,
        ?array $attributes = null,
    ): ?QuerySpan {
        return $this->persistEntry(function () use ($end, $attributes, $spanType, $driverName, $databaseName, $bindings, $duration, $sql) {
            $span = new QuerySpan(
                traceId: $this->tracer->currentTraceId() ?? '',
                parentSpanId: $this->tracer->currentSpan()?->spanId,
                sql: $sql,
                duration: $duration,
                bindings: $this->includeBindings ? $bindings : null,
                databaseName: $databaseName,
                driverName: $driverName,
                end: $end,
                spanType: $spanType
            );

            $span->addAttributes($attributes);

            return $span;
        });
    }
}

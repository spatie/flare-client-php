<?php

namespace Spatie\FlareClient\Recorders\QueryRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;

class QueryRecorder implements SpansRecorder
{
    use RecordsSpans;

    protected bool $includeBindings = true;

    protected bool $findOrigin = false;

    protected ?int $findOriginThreshold = null;

    public static function type(): string|RecorderType
    {
        return RecorderType::Query;
    }

    public function configure(array $config): void
    {
        $this->configureRecorder($config);

        $this->includeBindings = $config['include_bindings'] ?? true;
        $this->findOrigin = $config['find_origin'] ?? false;
        $this->findOriginThreshold = $config['find_origin_threshold'] ?? null;
    }

    public function record(
        string $sql,
        int $duration,
        ?array $bindings = null,
        ?string $databaseName = null,
        ?string $driverName = null,
        FlareSpanType $spanType = SpanType::Query,
        ?array $attributes = null,
    ): QuerySpan {
        return $this->persistEntry(function () use ($attributes, $spanType, $driverName, $databaseName, $bindings, $duration, $sql) {
            $span = new QuerySpan(
                $this->tracer->currentTraceId() ?? '',
                $this->tracer->currentSpan()?->spanId,
                $sql,
                $duration,
                $this->includeBindings ? $bindings : null,
                $databaseName,
                $driverName,
                $spanType
            );

            $span->addAttributes($attributes);

            if ($this->shouldFindOrigins($duration)) {
                $this->setQueryOrigins($span);
            }

            return $span;
        });
    }

    protected function shouldFindOrigins(int $duration): bool
    {
        return $this->shouldTrace()
            && $this->findOrigin
            && ($this->findOriginThreshold === null || $duration >= $this->findOriginThreshold);
    }

    protected function setQueryOrigins(QuerySpan $span): QuerySpan
    {
        $frame = $this->tracer->backTracer->firstApplicationFrame(20);

        if ($frame) {
            $span->setOriginFrame($frame);
        }

        return $span;
    }
}

<?php

namespace Spatie\FlareClient\Recorders\QueryRecorder;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpans;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\SpansRecorder;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Tracer;

class QueryRecorder implements SpansRecorder
{
    use RecordsSpans;

    public static function initialize(ContainerInterface $container, array $config): static
    {
        return new static(
            $container->get(Tracer::class),
            $config['trace_queries'],
            $config['report_queries'],
            $config['max_reported_queries'],
            $config['include_bindings'],
            $config['find_query_origin'],
            $config['find_query_origin_threshold'],
        );
    }

    public function __construct(
        protected Tracer $tracer,
        bool $traceQueries,
        bool $reportQueries,
        ?int $maxReportedQueries,
        protected bool $includeBindings,
        protected bool $findQueryOrigin,
        protected ?int $findQueryOriginThreshold,
    ) {
        $this->traceSpans = $traceQueries;
        $this->reportSpans = $reportQueries;
        $this->maxReportedSpans = $maxReportedQueries;
    }

    public function start(): void
    {

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

        if ($this->shouldFindOrigins($duration)) {
            $this->setQueryOrigins($span);
        }

        if ($attributes) {
            $span->setAttributes($attributes);
        }

        $this->persistSpan($span);

        return $span;
    }

    protected function shouldFindOrigins(int $duration): bool
    {
        return $this->shouldTraceSpans()
            && $this->findQueryOrigin
            && ($this->findQueryOriginThreshold === null || $duration >= $this->findQueryOriginThreshold);
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

<?php

namespace Spatie\FlareClient\Recorders\QueryRecorder;

use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Performance\Enums\SpanType;
use Spatie\FlareClient\Performance\Spans\Span;
use Spatie\FlareClient\Performance\Support\SpanId;

class QuerySpan extends Span
{
    /**
     * @param string $traceId
     * @param string $parentSpanId
     * @param string $sql
     * @param int $duration
     * @param array<int|string, mixed>|null $bindings
     * @param string|null $databaseName
     * @param string|null $driverName
     * @param SpanType $spanType
     */
    public function __construct(
        string $traceId,
        ?string $parentSpanId,
        public string $sql,
        public int $duration,
        public ?array $bindings = null,
        public ?string $databaseName = null,
        public ?string $driverName = null,
        public FlareSpanType $spanType = SpanType::Query,
    ) {
        $end = static::getCurrentTime();

        parent::__construct(
            traceId: $traceId,
            spanId: SpanId::generate(),
            parentSpanId: $parentSpanId,
            name: "Query - {$sql}",
            startUs: $end - $duration,
            endUs: $end,
            attributes: array_filter($this->collectAttributes()),
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_type' => $this->spanType,
            'db.system' => $this->driverName,
            'db.name' => $this->databaseName,
            'db.statement' => $this->sql,
            'db.sql.bindings' => $this->bindings,
        ];
    }
}

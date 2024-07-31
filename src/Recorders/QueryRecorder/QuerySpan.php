<?php

namespace Spatie\FlareClient\Recorders\QueryRecorder;

use Spatie\FlareClient\Concerns\HasOriginAttributes;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\SpanId;

class QuerySpan extends Span
{
    use HasOriginAttributes;

    /**
     * @param array<int|string, mixed>|null $bindings
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

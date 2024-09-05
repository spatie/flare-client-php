<?php

namespace Spatie\FlareClient\Recorders\TransactionRecorder;

use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;

class TransactionSpan extends Span
{
    public function __construct(
        string $traceId,
        ?string $parentSpanId,
        public FlareSpanType $spanType = SpanType::Transaction,
    ) {
        parent::__construct(
            traceId: $traceId,
            spanId: self::ids()->span(),
            parentSpanId: $parentSpanId,
            name: 'DB Transaction',
            start: static::getCurrentTime(),
            end: null,
            attributes: array_filter($this->collectAttributes()),
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_type' => $this->spanType,
        ];
    }
}

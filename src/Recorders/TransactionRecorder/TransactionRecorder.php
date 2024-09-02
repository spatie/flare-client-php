<?php

namespace Spatie\FlareClient\Recorders\TransactionRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;

class TransactionRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Transaction;
    }

    public function recordBegin(
        FlareSpanType $spanType = SpanType::Transaction,
        ?array $attributes = null,
    ): ?TransactionSpan {
        return $this->startSpan(fn () => (new TransactionSpan(
            traceId: $this->tracer->currentTraceId() ?? '',
            parentSpanId: $this->tracer->currentSpan()?->spanId,
            spanType: $spanType,
        ))->addAttributes($attributes));
    }

    public function recordCommit(
        ?array $attributes = null,
    ): ?TransactionSpan {
        return $this->endSpan(
            fn (TransactionSpan $span) => $span->addAttributes([
                'db.transaction.status' => 'committed',
                ...$attributes,
            ])
        );
    }

    public function recordRollback(
        ?array $attributes = null,
    ): ?TransactionSpan {
        return $this->endSpan(
            fn (TransactionSpan $span) => $span->addAttributes([
                'db.transaction.status' => 'rolled back',
                ...$attributes,
            ])
        );
    }
}

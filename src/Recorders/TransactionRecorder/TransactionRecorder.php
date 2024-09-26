<?php

namespace Spatie\FlareClient\Recorders\TransactionRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\FlareSpanType;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Enums\TransactionStatus;


class TransactionRecorder implements SpansRecorder
{
    /** @use RecordsPendingSpans<TransactionSpan> */
    use RecordsPendingSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Transaction;
    }

    public function recordBegin(
        FlareSpanType $spanType = SpanType::Transaction,
        array $attributes = [],
    ): ?TransactionSpan {
        return $this->startSpan(fn (): TransactionSpan => (new TransactionSpan(
            traceId: $this->tracer->currentTraceId() ?? '',
            parentSpanId: $this->tracer->currentSpan()?->spanId,
            spanType: $spanType,
        ))->addAttributes($attributes));
    }

    public function recordCommit(
        array $attributes = [],
    ): ?TransactionSpan {
        return $this->endSpan(
            fn (TransactionSpan $span) => $span->addAttributes([
                'db.transaction.status' => TransactionStatus::Committed,
                ...$attributes,
            ])
        );
    }

    public function recordRollback(
        array $attributes = [],
    ): ?TransactionSpan {
        return $this->endSpan(
            fn (TransactionSpan $span) => $span->addAttributes([
                'db.transaction.status' => TransactionStatus::RolledBack,
                ...$attributes,
            ])
        );
    }
}

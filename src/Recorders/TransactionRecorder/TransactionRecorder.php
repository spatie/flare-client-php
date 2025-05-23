<?php

namespace Spatie\FlareClient\Recorders\TransactionRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Enums\TransactionStatus;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\Span;

class TransactionRecorder extends Recorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Transaction;
    }

    public function recordBegin(
        array $attributes = [],
    ): ?Span {
        return $this->startSpan(
            name: 'DB Transaction',
            attributes: fn () => array_filter([
                'flare.span_type' => SpanType::Transaction,
                ...$attributes,
            ]),
        );
    }

    public function recordCommit(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes:  [
            'db.transaction.status' => TransactionStatus::Committed,
            ...$attributes,
        ]);
    }

    public function recordRollback(
        array $attributes = [],
    ): ?Span {
        return $this->endSpan(additionalAttributes:  [
            'db.transaction.status' => TransactionStatus::RolledBack,
            ...$attributes,
        ]);
    }
}

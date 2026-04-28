<?php

namespace Spatie\FlareClient\Recorders\QueueRecorder;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;

class QueueRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Queue;
    }

    public function recordStart(
        string $jobName,
        array $attributes = [],
    ): ?Span {
        // TODO: basically we need to know if this job is ignored (by sampler?) because if it is so, pause tracing until the job is queued, and then continue the trace from there

        return $this->startSpan(
            name: "Queueing - {$jobName}",
            attributes: [
                'flare.span_type' => SpanType::QueueingJob,
                ...$attributes,
            ],
        );
    }

    public function recordEnd(array $attributes = []): ?Span
    {
        return $this->endSpan(additionalAttributes: $attributes);
    }
}

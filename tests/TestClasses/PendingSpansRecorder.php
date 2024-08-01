<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;

class PendingSpansRecorder implements \Spatie\FlareClient\Contracts\Recorders\SpansRecorder
{
    use RecordsPendingSpans;

    public static function type(): string|RecorderType
    {
        return 'pending_spans';
    }

    public function pushSpan(string $name): ?Span
    {
        return $this->startSpan(Span::build(
            traceId: $this->tracer->currentTraceId() ?? '',
            name: $name,
            parentId: $this->tracer->currentSpanId(),
        ));
    }

    public function popSpan(): ?Span
    {
        return $this->endSpan();
    }
}

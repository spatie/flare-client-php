<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Concerns\RecordsSpans;
use Spatie\FlareClient\Contracts\SpansRecorder as BaseSpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;

class SpansRecorder implements BaseSpansRecorder
{
    use RecordsSpans;

    public static function type(): string|RecorderType
    {
        return 'spans';
    }

    public function record(string $message): ?Span
    {
        return $this->persistEntry(Span::build(
            traceId: $this->tracer->isSamping() ? $this->tracer->currentTraceId() : '',
            name: "Span - {$message}",
            attributes: [
                'message' => $message,
            ],
        ));
    }
}

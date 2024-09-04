<?php

namespace Spatie\FlareClient\Recorders\FilesystemRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;

class FilesystemRecorder implements SpansRecorder
{
    use RecordsPendingSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Filesystem;
    }

    public function recordOperationStart(
        string $name,
        array $attributes,
    ) {
        $this->startSpan(fn () => Span::build(
            $this->tracer->currentTraceId() ?? '',
            $this->tracer->currentSpanId(),
            name: "Filesystem - {$name}",
            attributes: $attributes,
        ));
    }

    public function recordOperationEnd(
        ?array $attributes = null,
    ) {
        $this->endSpan(attributes: $attributes);
    }
}

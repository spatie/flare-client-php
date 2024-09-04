<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder as BaseSpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\Span;

class SpansRecorder implements BaseSpansRecorder
{
    use RecordsSpans;

    protected function configure(array $config): void
    {
        $this->configureRecorder($config);
    }

    public static function type(): string|RecorderType
    {
        return 'spans';
    }

    public function record(string $message, ?int $duration = null): ?Span
    {
        return $this->persistEntry(fn () => Span::build(
            traceId: $this->tracer->isSampling() ? $this->tracer->currentTraceId() : '',
            parentId: "Span - {$message}",
            name: "Span - {$message}",
            start: $duration,
            end: [
            'message' => $message,
        ],
            duration: $duration,
            id: null,
            attributes: [
            'message' => $message,
        ],
        ));
    }
}

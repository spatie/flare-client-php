<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder as BaseSpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\SpanEvent;

class SpanEventsRecorder implements BaseSpanEventsRecorder
{
    use RecordsSpanEvents;

    protected function configure(array $config): void
    {
        $this->configureRecorder(['find_origin_threshold' => null] + $config);
    }

    public static function type(): string|RecorderType
    {
        return 'span_events';
    }

    public function record(string $message): ?SpanEvent
    {
        return $this->persistEntry(fn () => SpanEvent::build(
            name: "Span Event - {$message}",
            attributes: [
                'message' => $message,
            ],
        ));
    }
}

<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder as BaseSpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\SpanEvent;

class SpanEventsRecorder implements BaseSpanEventsRecorder
{
    use RecordsSpanEvents;

    public static function type(): string|RecorderType
    {
        return 'span_events';
    }

    public function record(string $message): ?SpanEvent
    {
        $spanEvent = SpanEvent::build(
            name: "Span Event - {$message}",
            attributes: [
                'message' => $message,
            ],
        );

        return $this->persistEntry($spanEvent);
    }
}

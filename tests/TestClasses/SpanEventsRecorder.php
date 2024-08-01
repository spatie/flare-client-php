<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Exception;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\SpanEventsRecorder as BaseSpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Tracer;

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

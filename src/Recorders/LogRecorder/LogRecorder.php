<?php

namespace Spatie\FlareClient\Recorders\LogRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\SpanEvent;

class LogRecorder  extends Recorder  implements SpanEventsRecorder
{
    /** @use RecordsSpanEvents<SpanEvent> */
    use RecordsSpanEvents;

    public static function type(): string|RecorderType
    {
        return RecorderType::Log;
    }

    public function record(
        ?string $message,
        MessageLevels $level = MessageLevels::Info,
        array $context = [],
        array $attributes = [],
    ): ?SpanEvent {
        return $this->spanEvent(
            name: "Log entry",
            attributes: fn () => [
                'flare.span_event_type' => SpanEventType::Log,
                'log.message' => $message,
                'log.level' => $level->value,
                'log.context' => $context,
                ...$attributes,
            ],
        );
    }
}

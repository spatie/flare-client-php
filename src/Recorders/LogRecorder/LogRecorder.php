<?php

namespace Spatie\FlareClient\Recorders\LogRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\Recorder;

class LogRecorder  extends Recorder  implements SpanEventsRecorder
{
    /** @use RecordsSpanEvents<LogMessageSpanEvent> */
    use RecordsSpanEvents;

    public static function type(): string|RecorderType
    {
        return RecorderType::Log;
    }

    public function record(
        ?string $message,
        MessageLevels $level = MessageLevels::Info,
        array $context = [],
        ?int $time = null,
        FlareSpanEventType $spanEventType = SpanEventType::Log,
        ?array $attributes = null,
    ): ?LogMessageSpanEvent {
        return $this->persistEntry(fn () => (new LogMessageSpanEvent(
            message: $message,
            level: $level,
            context: $context,
            time: $time,
            spanEventType: $spanEventType,
        ))->addAttributes($attributes));
    }
}

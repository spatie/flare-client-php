<?php

namespace Spatie\FlareClient\Recorders\GlowRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;

class GlowRecorder implements SpanEventsRecorder
{
    /**  @use RecordsSpanEvents<GlowSpanEvent> */
    use RecordsSpanEvents;

    public static function type(): string|RecorderType
    {
        return RecorderType::Glow;
    }

    public function record(
        string $name,
        string $level = MessageLevels::INFO,
        array $context = [],
        ?int $time = null,
        FlareSpanEventType $spanEventType = SpanEventType::Glow,
        ?array $attributes = null,
    ): ?GlowSpanEvent {
        return $this->persistEntry(fn () => (new GlowSpanEvent(
            name: $name,
            level: $level,
            context: $context,
            time: $time,
            spanEventType: $spanEventType,
        ))->addAttributes($attributes));
    }
}

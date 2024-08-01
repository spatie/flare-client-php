<?php

namespace Spatie\FlareClient\Recorders\GlowRecorder;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Concerns\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Contracts\SpanEventsRecorder;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\FlareMiddleware\AddGlows;
use Spatie\FlareClient\Tracer;

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

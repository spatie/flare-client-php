<?php

namespace Spatie\FlareClient\Recorders\GlowRecorder;

use Monolog\Level;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Spans\SpanEvent;

class GlowRecorder extends SpanEventsRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Glow;
    }

    public function record(
        string $name,
        Level $level = Level::Info,
        array $context = [],
        array $attributes = [],
    ): ?SpanEvent {
        return $this->spanEvent(
            name: "Glow - {$name}",
            attributes: fn () => [
                'flare.span_event_type' => SpanEventType::Glow,
                'glow.name' => $name,
                'glow.level' => strtolower($level->getName()),
                'glow.context' => $context,
                ...$attributes,
            ],
        );
    }
}

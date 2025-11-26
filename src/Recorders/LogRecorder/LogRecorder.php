<?php

namespace Spatie\FlareClient\Recorders\LogRecorder;

use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Spans\SpanEvent;

class LogRecorder extends SpanEventsRecorder
{
    const DEFAULT_MINIMAL_LEVEL = MessageLevels::Debug;

    protected MessageLevels $minimalLevel;

    protected function configure(array $config): void
    {
        $this->minimalLevel = $config['minimal_level'] ?? self::DEFAULT_MINIMAL_LEVEL;
    }

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
        // TODO: move infrastructure here to use the logger

        if ($level->getOrder() > $this->minimalLevel->getOrder()) {
            return null;
        }

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

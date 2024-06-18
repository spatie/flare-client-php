<?php

namespace Spatie\FlareClient\Recorders\GlowRecorder;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Performance\Enums\SpanEventType;
use Spatie\FlareClient\Performance\Spans\SpanEvent;

class GlowSpanEvent extends SpanEvent
{
    protected string $glowName;

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $name,
        public string $level = MessageLevels::INFO,
        public array $context = [],
        ?int $time = null,
        public FlareSpanEventType $spanEventType = SpanEventType::Glow,
    ) {
        $this->glowName = $name;

        parent::__construct(
            name: "Glow - {$name}",
            timeUs: $time ?? static::getCurrentTime(),
            attributes: $this->collectAttributes(),
        );
    }

    /**
     * @return array{time: int, name: string, message_level: string, meta_data: array, microtime: float}
     */
    public function toOriginalFlareFormat(): array
    {
        return [
            'time' => (int) ($this->timeUs / 1000),
            'name' => $this->glowName,
            'message_level' => $this->level,
            'meta_data' => $this->context,
            'microtime' => (int) ($this->timeUs / 1000),
        ];
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'glow.name' => $this->glowName,
            'glow.level' => $this->level,
            'glow.context' => $this->context,
        ];
    }
}

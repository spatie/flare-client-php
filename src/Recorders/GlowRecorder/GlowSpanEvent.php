<?php

namespace Spatie\FlareClient\Recorders\GlowRecorder;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Enums\MessageLevels;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Spans\SpanEvent;

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
            timestamp: $time ?? static::getCurrentTime(),
            attributes: $this->collectAttributes(),
        );
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

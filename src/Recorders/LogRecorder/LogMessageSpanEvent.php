<?php

namespace Spatie\FlareClient\Recorders\LogRecorder;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Spans\SpanEvent;

class LogMessageSpanEvent extends SpanEvent
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public ?string $message,
        public string $level,
        public array $context,
        ?int $time = null,
        public FlareSpanEventType $spanEventType = SpanEventType::Log,
    ) {
        parent::__construct(
            name: "Log entry",
            timeUs: $time ?? static::getCurrentTime(),
            attributes: $this->collectAttributes(),
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'log.message' => $this->message,
            'log.level' => $this->level,
            'log.context' => $this->context,
        ];
    }
}

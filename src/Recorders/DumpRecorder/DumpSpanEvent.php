<?php

namespace Spatie\FlareClient\Recorders\DumpRecorder;

use Spatie\FlareClient\Concerns\HasOriginAttributes;
use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Enums\SpanEventType;
use Spatie\FlareClient\Spans\SpanEvent;

class DumpSpanEvent extends SpanEvent
{
    use HasOriginAttributes;

    public function __construct(
        public string $htmlDump,
        ?int $time = null,
        public FlareSpanEventType $spanEventType = SpanEventType::Dump,
    ) {
        parent::__construct(
            name: "Dump entry",
            timeUs: $time ?? static::getCurrentTime(),
            attributes: $this->collectAttributes(),
        );
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'dump.html' => $this->htmlDump,
        ];
    }
}

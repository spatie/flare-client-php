<?php

namespace Spatie\FlareClient\Recorders\DumpRecorder;

use Spatie\FlareClient\Contracts\FlareSpanEventType;
use Spatie\FlareClient\Performance\Concerns\HasOriginAttributes;
use Spatie\FlareClient\Performance\Enums\SpanEventType;
use Spatie\FlareClient\Performance\Spans\SpanEvent;

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

    public function toOriginalFlareFormat(): array
    {
        return [
            'html_dump' => $this->htmlDump,
            'file' => $this->attributes['code.filepath'],
            'line_number' =>  $this->attributes['code.lineno'],
            'microtime' => (int )($this->timeUs / 1000),
        ];
    }

    protected function collectAttributes(): array
    {
        return [
            'flare.span_event_type' => $this->spanEventType,
            'dump.html' => $this->htmlDump,
        ];
    }
}

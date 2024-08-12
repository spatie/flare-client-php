<?php

namespace Spatie\FlareClient\Support;

class TraceLimits
{
    public static function defaults(): self
    {
        return new self(
            maxSpans: 512,
            maxAttributesPerSpan: 128,
            maxSpanEventsPerSpan: 128,
            maxAttributesPerSpanEvent: 128,
        );
    }

    public function __construct(
        public int $maxSpans,
        public int $maxAttributesPerSpan,
        public int $maxSpanEventsPerSpan,
        public int $maxAttributesPerSpanEvent,
    ) {
    }
}

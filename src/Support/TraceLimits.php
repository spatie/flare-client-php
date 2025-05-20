<?php

namespace Spatie\FlareClient\Support;

class TraceLimits
{
    public function __construct(
        public int $maxSpans = 512,
        public int $maxAttributesPerSpan = 128,
        public int $maxSpanEventsPerSpan = 128,
        public int $maxAttributesPerSpanEvent = 128,
    ) {
    }
}

<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\LargeAttributesTrimmer;

trait LargeAttributesTrimmingRecorder
{
    protected function shouldTrimAttributes(): bool
    {
        return false;
    }

    protected function trimAttributes(Span|SpanEvent $entry): void
    {
        if (! $this->shouldTrimAttributes()) {
            return;
        }

        (new LargeAttributesTrimmer())->trim($entry, $this->tracer->limits['max_attribute_size_in_kb']);
    }
}

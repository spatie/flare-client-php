<?php

namespace Spatie\FlareClient\Concerns\Recorders;

use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;
use Spatie\FlareClient\Support\AttributeSizeLimiter;

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

        $budgetInKb = $this->tracer->limits['max_attribute_size_in_kb'];

        if ($budgetInKb <= 0) {
            return;
        }

        [$entry->attributes, $dropped] = (new AttributeSizeLimiter())->limit($entry->attributes, $budgetInKb * 1024);

        $entry->droppedAttributesCount += $dropped;
    }
}

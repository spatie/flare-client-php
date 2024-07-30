<?php

namespace Spatie\FlareClient\Contracts;

use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Spans\SpanEvent;

interface SpanEventsRecorder extends Recorder
{
    /** @return array<SpanEvent> */
    public function getSpanEvents(): array;
}

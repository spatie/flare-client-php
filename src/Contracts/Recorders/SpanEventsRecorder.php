<?php

namespace Spatie\FlareClient\Contracts\Recorders;

use Spatie\FlareClient\Spans\SpanEvent;

interface SpanEventsRecorder extends Recorder
{
    /** @return array<SpanEvent> */
    public function getSpanEvents(): array;
}

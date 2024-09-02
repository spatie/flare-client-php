<?php

namespace Spatie\FlareClient\Recorders\ThrowableRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpanEvents;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Report;
use Spatie\FlareClient\Tracer;

class ThrowableRecorder implements SpanEventsRecorder
{
    use RecordsSpanEvents;

    public function __construct(
        protected Tracer $tracer,
    ) {
        $this->trace = true;
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Exception;
    }

    public function record(Report $report): void
    {
        $this->persistEntry(fn () => ThrowableSpanEvent::fromReport($report));
    }
}

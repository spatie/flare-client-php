<?php

namespace Spatie\FlareClient\Recorders\ErrorRecorder;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanStatusCode;
use Spatie\FlareClient\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Report;

class ErrorRecorder extends SpanEventsRecorder
{
    const DEFAULT_WITH_TRACES = true;

    const DEFAULT_WITH_ERRORS = false;

    // TODO: remove this in favour of a tracer intance on the reporter which will write the error directly to the trace

    protected function configure(array $config): void
    {
        $this->withTraces = true;
        $this->withErrors = false;
    }

    public static function type(): string|RecorderType
    {
        return RecorderType::Exception;
    }

    public function record(array $report): void
    {
        if ($this->withTraces === false && $this->tracer->isSampling() === false) {
            return;
        }

        $currentSpan = $this->tracer->currentSpan();

        if ($currentSpan === null) {
            return;
        }

        $event = ErrorSpanEvent::fromReport(
            $report,
            $this->tracer->time->getCurrentTime(),
        );

        if ($event->handled !== true) {
            $currentSpan->setStatus(
                SpanStatusCode::Error,
                $event->message,
            );
        }

        $currentSpan->addEvent($event);
    }
}

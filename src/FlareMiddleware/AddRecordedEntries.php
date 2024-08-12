<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\Contracts\Recorders\Recorder;
use Spatie\FlareClient\Contracts\Recorders\SpanEventsRecorder;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\ReportFactory;

class AddRecordedEntries implements FlareMiddleware
{
    /**
     * @param array<Recorder> $recorders
     */
    public function __construct(
        public array $recorders,
    ) {
    }

    public function handle(ReportFactory $report, Closure $next)
    {
        foreach ($this->recorders as $recorder) {
            if($recorder instanceof SpanEventsRecorder) {
                $report->spanEvent(...$recorder->getSpanEvents());
            }

            if($recorder instanceof SpansRecorder) {
                $report->span(...$recorder->getSpans());
            }

            $recorder->reset();
        }

        return $next($report);
    }
}

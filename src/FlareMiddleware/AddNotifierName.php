<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Spatie\FlareClient\Performance\Support\Telemetry;
use Spatie\FlareClient\Report;

class AddNotifierName implements FlareMiddleware
{
    public function handle(Report $report, $next)
    {
        $report->notifierName(Telemetry::NAME);

        return $next($report);
    }
}

<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\ReportFactory;

interface FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): ReportFactory;
}

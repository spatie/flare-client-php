<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\ReportFactory;

interface FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next);
}

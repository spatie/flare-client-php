<?php

namespace Spatie\FlareClient\Tests\TestClasses;

use Closure;
use Spatie\FlareClient\FlareMiddleware\FlareMiddleware;
use Spatie\FlareClient\ReportFactory;

class FakeFlareMiddleware implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        return $next($report->context('extra', ['key' => 'value']));
    }
}

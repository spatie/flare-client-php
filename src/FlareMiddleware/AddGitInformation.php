<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\ReportFactory;

class AddGitInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): Closure|ReportFactory
    {
        $provider = new GitAttributesProvider();

        $report->addAttributes($provider->toArray());

        return $next($report);
    }
}

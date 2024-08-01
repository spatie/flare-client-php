<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\ReportFactory;

class AddGitInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next)
    {
        $provider = new GitAttributesProvider();

        $report->addAttributes($provider->toArray());

        return $next($report);
    }
}

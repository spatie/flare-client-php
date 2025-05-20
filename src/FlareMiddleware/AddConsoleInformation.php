<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\AttributesProviders\ConsoleAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Runtime;

class AddConsoleInformation implements FlareMiddleware
{
    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if (! $this->isRunningInConsole()) {
            return $next($report);
        }

        $provider = new ConsoleAttributesProvider();

        $report->addAttributes($provider->toArray($_SERVER['argv'] ?? []));

        return $next($report);
    }

    protected function isRunningInConsole(): bool
    {
        return Runtime::runningInConsole();
    }

    protected function buildProvider(): ConsoleAttributesProvider
    {
        return new ConsoleAttributesProvider();
    }
}

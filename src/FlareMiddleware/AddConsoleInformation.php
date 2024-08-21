<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\AttributesProviders\ConsoleAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Runtime;

class AddConsoleInformation implements FlareMiddleware
{
    // TODO: Since we can add command spans, why not use these spans to add more information to the report?
    // And provide the entry point with the span uuid?

    public function handle(ReportFactory $report, Closure $next)
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

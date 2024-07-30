<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\ConsoleAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Runtime;

class AddConsoleInformation implements FlareMiddleware
{
    public static function initialize(ContainerInterface $container, array $config): static
    {
        return new self();
    }

    public function handle(ReportFactory $report, Closure $next)
    {
        if (! Runtime::runningInConsole()) {
            return $next($report);
        }

        $provider = new ConsoleAttributesProvider();

        $report->addAttributes($provider->toArray($_SERVER['argv'] ?? []));

        return $next($report);
    }
}

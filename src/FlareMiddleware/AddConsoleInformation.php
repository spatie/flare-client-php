<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\ConsoleAttributesProvider;
use Spatie\FlareClient\AttributesProviders\GitAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Runtime;

class AddConsoleInformation implements FlareMiddleware
{
    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            $container->get(ConsoleAttributesProvider::class),
        );
    }

    public function __construct(
        protected ConsoleAttributesProvider $consoleAttributesProvider = new ConsoleAttributesProvider,
    ) {
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if (! $this->isRunningInConsole()) {
            return $next($report);
        }

        $report->addAttributes($this->consoleAttributesProvider->toArray($_SERVER['argv'] ?? []));

        return $next($report);
    }

    protected function isRunningInConsole(): bool
    {
        return Runtime::runningInConsole();
    }
}

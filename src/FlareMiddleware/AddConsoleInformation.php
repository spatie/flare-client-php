<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Runtime;

class AddConsoleInformation implements FlareMiddleware
{
    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self();
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if (! $this->isRunningInConsole()) {
            return $next($report);
        }

        $report->addAttributes([
            'process.command_args' => $_SERVER['argv'] ?? [],
        ]);

        return $next($report);
    }

    protected function isRunningInConsole(): bool
    {
        return Runtime::runningInConsole();
    }
}

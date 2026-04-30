<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\AttributesProviders\SymfonyRequestAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Support\Runtime;

class AddRequestInformation implements FlareMiddleware
{
    public function __construct(
        protected Redactor $redactor,
    ) {
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        if ($this->isRunningInConsole()) {
            return $next($report);
        }

        $report->addAttributes(
            $this->getAttributes()
        );

        return $next($report);
    }

    protected function isRunningInConsole(): bool
    {
        return Runtime::runningInConsole();
    }

    protected function getAttributes(): array
    {
        return (new SymfonyRequestAttributesProvider($this->redactor))->toArray();
    }
}

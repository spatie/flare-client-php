<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\ReportFactory;

class AddEntryPoint implements FlareMiddleware
{
    public function __construct(
        protected EntryPointResolver $entryPointResolver,
    ) {
    }

    public function handle(ReportFactory $report, Closure $next): ReportFactory
    {
        $report->addAttributes($this->entryPointResolver->get()->toAttributes());

        return $next($report);
    }
}

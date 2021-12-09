<?php

namespace Spatie\FlareClient\FlareMiddleware;

use ArrayObject;
use Closure;
use Spatie\FlareClient\Report;

class AddDocumentationLink implements FlareMiddleware
{
    protected ArrayObject $documentationLinkResolvers;

    public function __construct(ArrayObject $documentationLinkResolvers)
    {
        $this->documentationLinkResolvers = $documentationLinkResolvers;
    }

    public function handle(Report $report, Closure $next)
    {
        if (! $throwable = $report->getThrowable()) {
            return $next($report);
        }

        foreach ($this->documentationLinkResolvers as $resolver) {
            if ($link = $resolver($throwable)) {
                $report->addDocumentationLink($link);

                return $next($report);
            }
        }

        return $next($report);
    }
}

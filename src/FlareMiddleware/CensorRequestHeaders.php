<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Spatie\FlareClient\Report;

class CensorRequestHeaders implements FlareMiddleware
{
    public function __construct(
        protected array $headers = [
            'API-KEY',
            'Authorization',
            'Cookie',
            'Set-Cookie',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ]
    ) {
    }

    public function handle(Report $report, $next)
    {
        $context = $report->allContext();

        foreach ($this->headers as $header) {
            $header = strtolower($header);

            if (isset($context['headers'][$header])) {
                $context['headers'][$header] = '<CENSORED>';
            }
        }

        $report->userProvidedContext($context);

        return $next($report);
    }
}

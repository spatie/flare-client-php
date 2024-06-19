<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Spatie\FlareClient\Report;

class CensorRequestBodyFields implements FlareMiddleware
{
    public function __construct(protected array $fieldNames = ['password', 'password_confirmation'])
    {
    }

    public function handle(Report $report, $next)
    {
        $context = $report->allContext();

        foreach ($this->fieldNames as $fieldName) {
            if (isset($context['request_data']['body'][$fieldName])) {
                $context['request_data']['body'][$fieldName] = '<CENSORED>';
            }
        }

        $report->userProvidedContext($context);

        return $next($report);
    }
}

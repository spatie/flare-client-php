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
            $this->groupHeaders($this->getAttributes())
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

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, mixed>
     */
    protected function groupHeaders(array $attributes): array
    {
        $headers = [];

        foreach ($attributes as $key => $value) {
            if (! str_starts_with($key, 'http.request.header.')) {
                continue;
            }

            $headers[substr($key, strlen('http.request.header.'))] = implode(', ', $value);

            unset($attributes[$key]);
        }

        $attributes['http.request.headers'] = $headers;

        return $attributes;
    }
}

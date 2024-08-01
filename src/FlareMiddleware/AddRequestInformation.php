<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Runtime;
use Symfony\Component\HttpFoundation\Request;

class AddRequestInformation implements FlareMiddleware
{
    /** @var array<string> */
    public array $censorBodyFields = [];

    /** @var array<string> */
    public array $censorRequestHeaders = [];

    public bool $removeIp = false;

    public function configure(array $config): void
    {
        $this->censorBodyFields = $config['censor_body_fields'] ?? [];
        $this->censorRequestHeaders = $config['censor_request_headers'] ?? [];
        $this->removeIp = $config['remove_ip'] ?? false;
    }

    public function handle(ReportFactory $report, Closure $next)
    {
        if ($this->isRunningInConsole()) {
            return $next($report);
        }

        $request = $this->getRequest();

        $provider = $this->buildProvider($request);

        $report->addAttributes(
            $provider->toArray($request)
        );

        return $next($report);
    }

    protected function isRunningInConsole(): bool
    {
        return Runtime::runningInConsole();
    }

    protected function getRequest(): Request
    {
        return Request::createFromGlobals();
    }

    protected function buildProvider(Request $request): RequestAttributesProvider
    {
        return new RequestAttributesProvider(
            $this->censorBodyFields,
            $this->censorRequestHeaders,
            $this->removeIp,
        );
    }
}

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
    public static function initialize(ContainerInterface $container, array $config): static
    {
        return new static(
            $config['censor_body_fields'] ?? [],
            $config['censor_request_headers'] ?? [],
            $config['remove_ip'] ?? false,
        );
    }

    /**
     * @param array<string> $censorBodyFields
     * @param array<string> $censorRequestHeaders
     */
    public function __construct(
        public array $censorBodyFields = [],
        public array $censorRequestHeaders = [],
        public bool $removeIp = false,
    )
    {
    }

    public function handle(ReportFactory $report, Closure $next)
    {
        if (Runtime::runningInConsole()) {
            return $next($report);
        }

        $provider = new RequestAttributesProvider(
            $this->censorBodyFields,
            $this->censorRequestHeaders,
            $this->removeIp,
        );

        $report->addAttributes($provider->toArray(Request::createFromGlobals()));

        return $next($report);
    }
}

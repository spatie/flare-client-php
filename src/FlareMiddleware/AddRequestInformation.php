<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Support\Runtime;
use Symfony\Component\HttpFoundation\Request;

class AddRequestInformation implements FlareMiddleware
{
    /** @var array<string> */
    public array $censorBodyFields = [];

    /** @var array<string> */
    public array $censorRequestHeaders = [];

    public bool $removeIp = false;

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new self(
            new RequestAttributesProvider($container->get(Redactor::class)),
            $config
        );
    }

    public function __construct(
        protected RequestAttributesProvider $attributesProvider,
        array $config
    ) {
    }

    public function handle(ReportFactory $report, Closure $next)
    {
        if ($this->isRunningInConsole()) {
            return $next($report);
        }

        $request = $this->getRequest();

        $report->addAttributes(
            $this->attributesProvider->toArray($request)
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
}

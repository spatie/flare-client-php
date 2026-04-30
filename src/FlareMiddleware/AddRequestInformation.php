<?php

namespace Spatie\FlareClient\FlareMiddleware;

use Closure;
use Spatie\FlareClient\AttributesProviders\EmptyUserAttributesProvider;
use Spatie\FlareClient\AttributesProviders\RequestAttributesProvider;
use Spatie\FlareClient\AttributesProviders\UserAttributesProvider;
use Spatie\FlareClient\ReportFactory;
use Spatie\FlareClient\Support\Redactor;
use Spatie\FlareClient\Support\Runtime;
use Symfony\Component\HttpFoundation\Request;

class AddRequestInformation implements FlareMiddleware
{
    /** @var class-string<RequestAttributesProvider> */
    protected string $requestAttributesProvider;

    /** @var class-string<UserAttributesProvider> */
    protected string $userAttributesProvider;

    public function __construct(
        protected Redactor $redactor,
        array $config = [],
    ) {
        $this->requestAttributesProvider = $config['request_attributes_provider'] ?? RequestAttributesProvider::class;
        $this->userAttributesProvider = $config['user_attributes_provider'] ?? EmptyUserAttributesProvider::class;
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
        $request = Request::createFromGlobals();

        $provider = new $this->requestAttributesProvider(
            $this->redactor,
            $this->userAttributesProvider,
            $request,
        );

        return $provider->toArray();
    }
}

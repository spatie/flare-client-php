<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\Contracts\RouteAttributesProvider;

class PhpRouteAttributesProvider implements RouteAttributesProvider, EntryPointHandlerProvider
{
    protected string $method;

    public function __construct(
        protected ?string $route = null,
        ?string $method = null,
        protected ?string $handlerName = null,
    ) {
        $serverMethod = $_SERVER['REQUEST_METHOD'] ?? null;

        $this->method = strtoupper($method ?? (is_string($serverMethod) ? $serverMethod : 'GET'));
    }

    public function toArray(): array
    {
        if ($this->route === null) {
            return [];
        }

        return [
            'http.route' => $this->route,
        ];
    }

    public function route(): ?string
    {
        return $this->route;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function entryPointHandlerName(): ?string
    {
        return $this->handlerName;
    }

    public function entryPointHandlerType(): ?string
    {
        return 'php_request';
    }

    public function entryPointHandlerIdentifier(): ?string
    {
        if ($this->route === null) {
            return null;
        }

        return "{$this->method} {$this->route}";
    }
}

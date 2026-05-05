<?php

namespace Spatie\FlareClient\EntryPoint;

use Spatie\FlareClient\Enums\EntryPointType;
use Spatie\FlareClient\Support\Runtime;

class EntryPointResolver
{
    protected ?EntryPoint $entryPoint = null;

    public function get(): EntryPoint
    {
        return $this->entryPoint ??= $this->resolve();
    }

    public function set(EntryPoint $entryPoint): void
    {
        $this->entryPoint = $entryPoint;
    }

    public function clear(): void
    {
        $this->entryPoint = null;
    }

    protected function resolve(): EntryPoint
    {
        if (Runtime::runningInConsole()) {
            return new EntryPoint(
                type: EntryPointType::Cli,
                value: implode(' ', $_SERVER['argv'] ?? []),
            );
        }

        $scheme = ($_SERVER['HTTPS'] ?? 'off') !== 'off' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return new EntryPoint(
            type: EntryPointType::Web,
            value: "{$scheme}://{$host}{$uri}",
        );
    }
}

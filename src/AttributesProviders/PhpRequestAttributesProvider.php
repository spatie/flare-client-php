<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\RequestAttributesProvider;
use Spatie\FlareClient\Support\Redactor;

class PhpRequestAttributesProvider implements RequestAttributesProvider
{
    /**
     * @param array<string, string|int>|null $server
     * @param array<string, string>|null $headers
     */
    public function __construct(
        protected Redactor $redactor,
        protected ?array $server = null,
        protected ?array $headers = null,
    ) {
        $this->server ??= $_SERVER;
        $this->headers ??= $this->resolveHeaders();
    }

    public function toArray(): array
    {
        $payload = [
            'url.full' => $this->url(),
            'url.scheme' => $this->scheme(),
            'url.path' => $this->path(),
            'url.query' => $this->serverString('QUERY_STRING') ?? '',

            'server.address' => $this->serverString('SERVER_NAME') ?: $this->serverString('SERVER_ADDR'),
            'server.port' => $this->serverString('SERVER_PORT'),

            'user_agent.original' => $this->headerValue('User-Agent'),

            'http.request.method' => $this->method(),
        ];

        if ($this->redactor->shouldCensorClientIps() === false && $clientIp = $this->serverString('REMOTE_ADDR')) {
            $payload['client.address'] = $clientIp;
        }

        if (! empty($this->headers)) {
            $payload['http.request.headers'] = $this->redactor->censorHeaders($this->headers);
        }

        return array_filter(
            $payload,
            fn ($value) => $value !== null && $value !== '',
        );
    }

    public function url(): string
    {
        $scheme = $this->scheme();
        $host = $this->serverString('HTTP_HOST') ?: $this->serverString('SERVER_NAME');
        $uri = $this->serverString('REQUEST_URI');

        if ($scheme === null || $host === null || $uri === null) {
            return 'unknown';
        }

        return "{$scheme}://{$host}{$uri}";
    }

    public function path(): ?string
    {
        $uri = $this->serverString('REQUEST_URI');

        if ($uri === null) {
            return null;
        }

        $position = strpos($uri, '?');

        return $position === false ? $uri : substr($uri, 0, $position);
    }

    public function method(): string
    {
        return strtoupper($this->serverString('REQUEST_METHOD') ?? 'GET');
    }

    protected function scheme(): ?string
    {
        $https = $this->serverString('HTTPS');

        if ($https !== null && strtolower($https) !== 'off') {
            return 'https';
        }

        if ((int) $this->serverString('SERVER_PORT') === 443) {
            return 'https';
        }

        return $this->serverString('REQUEST_SCHEME') ?? 'http';
    }

    protected function serverString(string $key): ?string
    {
        $value = $this->server[$key] ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    protected function headerValue(string $name): ?string
    {
        $name = strtolower($name);

        foreach ($this->headers ?? [] as $key => $value) {
            if (strtolower($key) === $name) {
                return $value;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    protected function resolveHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            if (! empty($headers)) {
                return array_map(fn ($value) => (string) $value, $headers);
            }
        }

        $headers = [];

        foreach ($this->server ?? [] as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (str_starts_with($key, 'HTTP_')) {
                $headerName = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$headerName] = (string) $value;

                continue;
            }

            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $headerName = strtolower(str_replace('_', '-', $key));
                $headers[$headerName] = (string) $value;
            }
        }

        return $headers;
    }
}

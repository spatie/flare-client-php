<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
use Spatie\FlareClient\Support\Redactor;

class PhpResponseAttributesProvider implements ResponseAttributesProvider
{
    /** @param array<string, string>|null $headers */
    public function __construct(
        protected Redactor $redactor,
        protected ?int $statusCode = null,
        protected ?int $bodySize = null,
        protected ?array $headers = null,
    ) {
        $responseCode = http_response_code();

        $this->statusCode ??= is_int($responseCode) ? $responseCode : null;
        $this->headers ??= $this->resolveHeaders();
    }

    public function toArray(): array
    {
        $payload = [];

        if ($this->statusCode !== null) {
            $payload['http.response.status_code'] = $this->statusCode;
        }

        if ($this->bodySize !== null) {
            $payload['http.response.body.size'] = $this->bodySize;
        }

        if (! empty($this->headers)) {
            $payload['http.response.headers'] = $this->redactor->censorHeaders($this->headers);
        }

        return $payload;
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    /** @return array<string, string> */
    protected function resolveHeaders(): array
    {
        $headers = [];

        foreach (headers_list() as $header) {
            if (! str_contains($header, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $header, 2);

            $headers[$name] = isset($headers[$name])
                ? "{$headers[$name]}, ".trim($value)
                : trim($value);
        }

        return $headers;
    }
}

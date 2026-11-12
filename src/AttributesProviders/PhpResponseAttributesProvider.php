<?php

namespace Spatie\FlareClient\AttributesProviders;

use GuzzleHttp\Utils;
use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
use Spatie\FlareClient\Support\HttpHeaderAttributes;
use Spatie\FlareClient\Support\Redactor;

class PhpResponseAttributesProvider implements ResponseAttributesProvider
{
    /** @param array<string, string|array<int, string>>|null $headers */
    public function __construct(
        protected Redactor $redactor,
        protected ?int $statusCode = null,
        protected ?int $bodySize = null,
        protected ?array $headers = null,
    ) {
        $this->statusCode ??= $this->resolveStatusCode();
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

        return [
            ...$payload,
            ...HttpHeaderAttributes::response($this->headers ?? [], $this->redactor),
        ];
    }

    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    protected function resolveStatusCode(): ?int
    {
        $statusCode = http_response_code();

        return is_int($statusCode) ? $statusCode : null;
    }

    /** @return array<string, array<int, string>> */
    protected function resolveHeaders(): array
    {
        return Utils::headersFromLines(headers_list());
    }
}

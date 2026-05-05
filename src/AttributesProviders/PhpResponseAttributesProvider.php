<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
use Spatie\FlareClient\Support\Redactor;

class PhpResponseAttributesProvider implements ResponseAttributesProvider
{
    /** @param array<string, string> $headers */
    public function __construct(
        protected Redactor $redactor,
        protected ?int $statusCode = null,
        protected ?int $bodySize = null,
        protected array $headers = [],
    ) {
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
}

<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
use Spatie\FlareClient\Support\HttpHeaderAttributes;
use Spatie\FlareClient\Support\Redactor;
use Symfony\Component\HttpFoundation\Response;

class SymfonyResponseAttributesProvider implements ResponseAttributesProvider
{
    public function __construct(
        protected Redactor $redactor,
        protected Response $response,
    ) {
    }

    public function toArray(): array
    {
        return [
            'http.response.status_code' => $this->statusCode(),
            'http.response.body.size' => strlen($this->response->getContent() ?: ''),
            ...HttpHeaderAttributes::response($this->response->headers->all(), $this->redactor),
        ];
    }

    public function statusCode(): ?int
    {
        return $this->response->getStatusCode();
    }
}

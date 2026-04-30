<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\ResponseAttributesProvider;
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
        $headers = $this->response->headers->all();

        foreach ($headers as $name => $value) {
            $headers[$name] = implode($value);

            if (empty($headers[$name])) {
                unset($headers[$name]);
            }
        }

        return [
            'http.response.status_code' => $this->statusCode(),
            'http.response.body.size' => strlen($this->response->getContent() ?: ''),
            'http.response.headers' => $this->redactor->censorHeaders($headers),
        ];
    }

    public function statusCode(): ?int
    {
        return $this->response->getStatusCode();
    }
}

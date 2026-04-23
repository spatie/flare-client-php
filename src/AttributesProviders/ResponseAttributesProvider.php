<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Support\Redactor;
use Symfony\Component\HttpFoundation\Response;

class ResponseAttributesProvider
{
    public function __construct(
        protected Redactor $redactor,
    ) {
    }

    public function toArray(Response $response): array
    {
        $headers = $response->headers->all();

        foreach ($headers as $name => $value) {
            $headers[$name] = implode($value);

            if (empty($headers[$name])) {
                unset($headers[$name]);
            }
        }

        return [
            'http.response.status_code' => $response->getStatusCode(),
            'http.response.body.size' => strlen($response->getContent() ?: ''),
            'http.response.headers' => $this->redactor->censorHeaders($headers),
        ];
    }
}

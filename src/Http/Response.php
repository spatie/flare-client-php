<?php

namespace Spatie\FlareClient\Http;

class Response
{
    public function __construct(
        protected mixed $headers,
        protected mixed $body,
        protected mixed $error
    ) {
    }

    public function getHeaders(): mixed
    {
        return $this->headers;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    public function hasBody(): bool
    {
        return $this->body != false;
    }

    public function getError(): mixed
    {
        return $this->error;
    }

    public function getHttpResponseCode(): ?int
    {
        if (! isset($this->headers['http_code'])) {
            return null;
        }

        return (int) $this->headers['http_code'];
    }
}

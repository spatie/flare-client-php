<?php

namespace Spatie\FlareClient\Http;

class Response
{
    protected $headers;

    protected $body;

    protected $error;

    public function __construct($headers, $body, $error)
    {
        $this->headers = $headers;

        $this->body = $body;

        $this->error = $error;
    }

    /**
     * @return mixed
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @return mixed
     */
    public function getBody()
    {
        return $this->body;
    }

    public function hasBody(): bool
    {
        return $this->body != false;
    }

    /**
     * @return mixed
     */
    public function getError()
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

<?php

namespace Spatie\FlareClient\Http;

use Spatie\FlareClient\Http\Exceptions\MissingParameter;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;

class Client
{
    public function __construct(
        protected ?string $apiToken = null,
        protected string $baseUrl = 'https://reporting.flareapp.io/api',
        protected int $timeout = 10,
        protected Sender $sender = new CurlSender()
    ) {
        if (! $baseUrl) {
            throw MissingParameter::create('baseUrl');
        }

        if (! $timeout) {
            throw MissingParameter::create('timeout');
        }
    }

    public function setApiToken(string $apiToken): self
    {
        $this->apiToken = $apiToken;

        return $this;
    }

    public function apiTokenSet(): bool
    {
        return ! empty($this->apiToken);
    }

    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = $baseUrl;

        return $this;
    }

    public function post(string $url, array $arguments = [])
    {
        return $this->sender->post("{$this->baseUrl}/{$url}", $this->apiToken, $arguments);
    }
}

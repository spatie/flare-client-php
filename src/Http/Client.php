<?php

namespace Spatie\FlareClient\Http;

use Spatie\FlareClient\Http\Exceptions\MissingParameter;
use Spatie\FlareClient\Senders\CurlSender;
use Spatie\FlareClient\Senders\Sender;

class Client
{
    protected ?string $apiToken;

    protected ?string $baseUrl;

    protected int $timeout;

    protected $lastRequest = null;

    protected Sender $sender;

    public function __construct(
        ?string $apiToken = null,
        string $baseUrl = 'https://reporting.flareapp.io/api',
        int $timeout = 10,
        ?Sender $sender = null
    ) {
        $this->apiToken = $apiToken;

        if (! $baseUrl) {
            throw MissingParameter::create('baseUrl');
        }

        $this->baseUrl = $baseUrl;

        if (! $timeout) {
            throw MissingParameter::create('timeout');
        }

        $this->timeout = $timeout;
        $this->sender = $sender ?? new CurlSender();
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

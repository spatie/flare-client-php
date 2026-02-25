<?php

namespace Spatie\FlareDaemon;

use React\Http\Browser as ReactBrowser;
use React\Promise\PromiseInterface;
use Spatie\FlareDaemon\Contracts\Browser as BrowserContract;

class Browser implements BrowserContract
{
    public function __construct(
        private ReactBrowser $browser,
    ) {
    }

    /**
     * @param array<string, string> $headers
     *
     * @return PromiseInterface<\Psr\Http\Message\ResponseInterface>
     */
    public function post(string $url, array $headers = [], string $body = ''): PromiseInterface
    {
        return $this->browser->post($url, $headers, $body);
    }

    /**
     * @param array<string, string> $headers
     *
     * @return PromiseInterface<\Psr\Http\Message\ResponseInterface>
     */
    public function get(string $url, array $headers = []): PromiseInterface
    {
        return $this->browser->get($url, $headers);
    }
}

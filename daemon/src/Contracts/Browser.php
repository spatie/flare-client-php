<?php

namespace Spatie\FlareDaemon\Contracts;

use React\Promise\PromiseInterface;

interface Browser
{
    /**
     * @param array<string, string> $headers
     *
     * @return PromiseInterface<\Psr\Http\Message\ResponseInterface>
     */
    public function post(string $url, array $headers = [], string $body = ''): PromiseInterface;

    /**
     * @param array<string, string> $headers
     *
     * @return PromiseInterface<\Psr\Http\Message\ResponseInterface>
     */
    public function get(string $url, array $headers = []): PromiseInterface;
}

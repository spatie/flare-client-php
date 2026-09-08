<?php

namespace Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle;

use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Spatie\FlareClient\Flare;

class FlareHandlerStack
{
    /**
     * @param (callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed>)|null $handler
     *
     * @return HandlerStack<callable(RequestInterface, array<array-key, mixed>): PromiseInterface<ResponseInterface, mixed>>
     */
    public static function create(
        Flare $flare,
        ?callable $handler = null
    ): HandlerStack {
        $stack = HandlerStack::create($handler);

        $stack->push(new FlareMiddleware($flare), 'flare');

        return $stack;
    }
}

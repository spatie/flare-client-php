<?php

namespace Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle;

use GuzzleHttp\HandlerStack;
use Spatie\FlareClient\Flare;

class FlareHandlerStack
{
    public static function create(
        Flare $flare,
        ?callable $handler = null
    ): HandlerStack {
        $stack = new HandlerStack($handler);

        $stack->push(new FlareMiddleware($flare));

        return $stack;
    }
}

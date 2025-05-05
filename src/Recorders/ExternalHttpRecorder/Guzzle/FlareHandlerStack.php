<?php

namespace Spatie\FlareClient\Recorders\ExternalHttpRecorder\Guzzle;

use GuzzleHttp\HandlerStack;
use Spatie\FlareClient\Flare;

class FlareHandlerStack extends HandlerStack
{
    public function __construct(
        Flare $flare,
        ?callable $handler = null
    ) {
        parent::__construct($handler);

        $this->push((new FlareMiddleware($flare)));
    }
}

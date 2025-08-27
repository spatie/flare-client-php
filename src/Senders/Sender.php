<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Support\Response;

interface Sender
{
    /**
     * @param Closure(Response): void $callback
     */
    public function post(
        string $endpoint,
        string $apiToken,
        array $payload,
        FlarePayloadType $type,
        Closure $callback,
    ): void;
}

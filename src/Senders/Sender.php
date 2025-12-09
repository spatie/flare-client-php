<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Enums\SpanKind;
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
        FlareEntityType $type,
        bool $test,
        Closure $callback,
    ): void;
}

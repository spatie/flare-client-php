<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlarePayloadType;
use Spatie\FlareClient\Senders\Support\Response;

class NullSender extends AbstractSender
{
    public function post(string $endpoint, string $apiToken, array $payload, FlarePayloadType $type, Closure $callback): void
    {
        $callback(new Response(200, []));
    }
}

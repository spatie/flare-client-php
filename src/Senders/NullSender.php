<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use Spatie\FlareClient\Senders\Support\Response;

class NullSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload, Closure $callback): void
    {
        $callback(new Response(200, []));
    }
}

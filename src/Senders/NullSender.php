<?php

namespace Spatie\FlareClient\Senders;

use Spatie\FlareClient\Senders\Support\Response;
use Closure;


class NullSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload, Closure $callback): void
    {
        $callback(new Response(200, []));
    }
}

<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\Support\Response;

class NullSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        $callback(new Response(200, []));
    }
}

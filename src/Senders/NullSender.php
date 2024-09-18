<?php

namespace Spatie\FlareClient\Senders;

use Spatie\FlareClient\Senders\Support\Response;

class NullSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): Response
    {
        return new Response(200, []);
    }
}

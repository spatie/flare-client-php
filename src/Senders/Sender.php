<?php

namespace Spatie\FlareClient\Senders;

use Spatie\FlareClient\Senders\Support\Response;

interface Sender
{
    public function post(
        string $endpoint,
        string $apiToken,
        array $payload
    ): Response;
}

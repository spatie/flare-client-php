<?php

namespace Spatie\FlareClient\Senders;

interface Sender
{
    public function post(
        string $endpoint,
        string $apiToken,
        array $payload
    ): array;
}

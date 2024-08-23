<?php

namespace Spatie\FlareClient\Senders;

class NullSender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        return [];
    }
}

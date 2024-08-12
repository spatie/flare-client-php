<?php

namespace Spatie\FlareClient\Senders;

class RaySender implements Sender
{
    public function post(string $endpoint, string $apiToken, array $payload): array
    {
        ray($payload)->label($endpoint);

        return [];
    }
}

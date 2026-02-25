<?php

namespace Spatie\FlareClient\Senders;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;

class DaemonSender implements Sender
{
    protected string $daemonUrl;

    public function __construct(
        protected array $config = []
    ) {
        $this->daemonUrl = $this->config['daemon_url'] ?? '127.0.0.1:8787';
    }

    public function post(string $endpoint, string $apiToken, array $payload, FlareEntityType $type, bool $test, Closure $callback): void
    {
        // Full implementation in US-004
    }
}

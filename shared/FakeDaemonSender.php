<?php

namespace Spatie\FlareClient\Tests\Shared;

use Closure;
use Spatie\FlareClient\Enums\FlareEntityType;
use Spatie\FlareClient\Senders\DaemonSender;
use Spatie\FlareClient\Senders\Sender;
use Spatie\FlareClient\Senders\Support\Response;

class FakeDaemonSender extends DaemonSender
{
    /** @var list<array{message: string, context: array<string, mixed>}> */
    public array $warnings = [];

    protected ?Closure $sendHandler = null;

    protected ?Sender $fallbackSender = null;

    public function onSend(Closure $handler): static
    {
        $this->sendHandler = $handler;

        return $this;
    }

    public function withFallbackSender(Sender $sender): static
    {
        $this->fallbackSender = $sender;

        return $this;
    }

    protected function sendToDaemon(
        FlareEntityType $type,
        string $apiToken,
        array $payload,
        bool $test,
        int $timeout,
    ): Response {
        return ($this->sendHandler)($type, $apiToken, $payload, $test, $timeout);
    }

    protected function createFallbackSender(): Sender
    {
        return $this->fallbackSender ?? parent::createFallbackSender();
    }

    protected function logWarning(string $message, array $context = []): void
    {
        $this->warnings[] = ['message' => $message, 'context' => $context];
    }
}

<?php

namespace Tests;

class PendingConnection
{
    private Connection $connection;

    /** @var array<int, array{type: string, data: string, test: bool}> */
    private array $payloads = [];

    /** @var array<int, string> */
    private array $commands = [];

    public function __construct()
    {
        $this->connection = new Connection();
    }

    public function withPayload(string $type, string $data): self
    {
        $this->payloads[] = [
            'type' => $type,
            'data' => $data,
            'test' => false,
        ];

        return $this;
    }

    public function withTestPayload(string $type, string $data): self
    {
        $this->payloads[] = [
            'type' => "{$type}_test",
            'data' => $data,
            'test' => true,
        ];

        return $this;
    }

    public function withPing(): self
    {
        $this->commands[] = 'PING';

        return $this;
    }

    public function withStatus(): self
    {
        $this->commands[] = 'STATUS';

        return $this;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    /**
     * @return array<int, array{type: string, data: string, test: bool}>
     */
    public function payloads(): array
    {
        return $this->payloads;
    }

    /**
     * @return array<int, string>
     */
    public function commands(): array
    {
        return $this->commands;
    }

    /**
     * Build a frame string for a single payload.
     */
    public static function buildFrame(string $type, string $data): string
    {
        $message = "v1:{$type}:{$data}";

        return strlen($message) . ":{$message}";
    }
}

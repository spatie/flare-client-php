<?php

namespace Tests;

use Spatie\FlareDaemon\Ingest;

class TcpServerFake
{
    /** @var array<int, Connection> */
    private array $connections = [];

    public function __construct(
        private Ingest $ingest,
    ) {
    }

    /**
     * Create a new fake connection and add it to tracking.
     */
    public function connect(): Connection
    {
        $connection = new Connection();
        $this->connections[] = $connection;

        return $connection;
    }

    /**
     * Simulate a connection sending a payload and receiving it through the Server's handlers.
     * This bypasses the TCP server and directly invokes the Ingest buffer methods.
     */
    public function sendPayload(string $type, string $data): Connection
    {
        $connection = $this->connect();

        $this->ingest->buffer($type, $data);

        $connection->write("2:OK");

        return $connection;
    }

    /**
     * Simulate a connection sending a test payload.
     */
    public function sendTestPayload(string $baseType, string $data): Connection
    {
        $connection = $this->connect();

        $this->ingest->bufferTest($baseType, $data, $connection);

        $connection->write("2:OK");

        return $connection;
    }

    /**
     * Process a PendingConnection's prepared payloads and commands.
     */
    public function process(PendingConnection $pending): Connection
    {
        $connection = $pending->connection();
        $this->connections[] = $connection;

        foreach ($pending->commands() as $command) {
            if ($command === 'PING') {
                $connection->write("2:OK");
            } elseif ($command === 'STATUS') {
                $status = $this->ingest->status();
                $json = json_encode($status);

                if ($json === false) {
                    $json = '{}';
                }

                $connection->write(strlen($json) . ":{$json}");
            }
        }

        foreach ($pending->payloads() as $payload) {
            if ($payload['test']) {
                $baseType = str_replace('_test', '', $payload['type']);
                $this->ingest->bufferTest($baseType, $payload['data'], $connection);
            } else {
                $this->ingest->buffer($payload['type'], $payload['data']);
            }

            $connection->write("2:OK");
        }

        return $connection;
    }

    /**
     * @return array<int, Connection>
     */
    public function connections(): array
    {
        return $this->connections;
    }
}

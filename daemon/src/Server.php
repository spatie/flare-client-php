<?php

namespace Spatie\FlareDaemon;

use Closure;
use React\Socket\ConnectionInterface;
use React\Socket\TcpServer;

class Server
{
    private ?TcpServer $server = null;

    /**
     * @param Closure(string, string): void $onPayload
     * @param Closure(string, string, ConnectionInterface): void $onTestPayload
     * @param Closure(): array<string, mixed> $onStatus
     */
    public function __construct(
        private Loop $loop,
        private OutputWriter $output,
        private Closure $onPayload,
        private Closure $onTestPayload,
        private Closure $onStatus,
    ) {
    }

    public function listen(string $address): void
    {
        $server = new TcpServer($address, $this->loop->get());

        $server->on('connection', function (ConnectionInterface $connection): void {
            $this->handleConnection($connection);
        });

        $server->on('error', function (\Throwable $e): void {
            $this->output->writeLn("Server error: {$e->getMessage()}");
        });

        $this->server = $server;

        $this->output->writeLn("Listening on {$address}");
    }

    public function close(): void
    {
        $this->server?->close();
        $this->server = null;
    }

    private function handleConnection(ConnectionInterface $connection): void
    {
        $payload = new Payload();

        $connection->on('data', function (string $data) use ($connection, &$payload): void {
            /** @var Payload $payload */
            $this->handleData($connection, $payload, $data);
        });

        $connection->on('close', function (): void {
            // Connection closed gracefully
        });

        $connection->on('error', function (\Throwable $e): void {
            $this->output->writeLn("Connection error: {$e->getMessage()}");
        });
    }

    private function handleData(ConnectionInterface $connection, Payload &$payload, string $data): void
    {
        if ($this->handleCommand($connection, $data)) {
            return;
        }

        $payload->append($data);

        $this->processCompletedPayloads($connection, $payload);
    }

    private function processCompletedPayloads(ConnectionInterface $connection, Payload &$payload): void
    {
        while ($payload->isComplete()) {
            if (! $payload->isValid()) {
                $version = $payload->version() ?? 'unknown';
                $this->output->writeLn("Invalid payload: unsupported version '{$version}'");
                $connection->write("2:OK");
                $payload = new Payload();

                return;
            }

            $type = $payload->type();
            $payloadData = $payload->data();

            if ($type === null || $payloadData === null) {
                $payload = new Payload();

                return;
            }

            $connection->write("2:OK");

            if ($payload->isTest()) {
                ($this->onTestPayload)($payload->baseType() ?? $type, $payloadData, $connection);
            } else {
                ($this->onPayload)($type, $payloadData);
            }

            $payload->reset();
        }
    }

    private function handleCommand(ConnectionInterface $connection, string $data): bool
    {
        $trimmed = trim($data);

        if ($trimmed === 'PING') {
            $connection->write("2:OK");

            return true;
        }

        if ($trimmed === 'STATUS') {
            $status = ($this->onStatus)();
            $json = json_encode($status);

            if ($json === false) {
                $json = '{}';
            }

            $length = strlen($json);
            $connection->write("{$length}:{$json}");

            return true;
        }

        return false;
    }
}

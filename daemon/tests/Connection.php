<?php

namespace Tests;

use Evenement\EventEmitter;
use PHPUnit\Framework\Assert;
use React\Socket\ConnectionInterface;
use React\Stream\WritableStreamInterface;

class Connection extends EventEmitter implements ConnectionInterface
{
    /** @var array<int, string> */
    private array $writtenData = [];

    private bool $closed = false;

    public function getRemoteAddress(): ?string
    {
        return '127.0.0.1:12345';
    }

    public function getLocalAddress(): ?string
    {
        return '127.0.0.1:8787';
    }

    public function isReadable(): bool
    {
        return ! $this->closed;
    }

    public function pause(): void
    {
    }

    public function resume(): void
    {
    }

    /**
     * @param WritableStreamInterface $dest
     * @param array<string, mixed> $options
     */
    public function pipe(WritableStreamInterface $dest, array $options = []): WritableStreamInterface
    {
        return $dest;
    }

    public function close(): void
    {
        $this->closed = true;
        $this->emit('close');
    }

    public function isWritable(): bool
    {
        return ! $this->closed;
    }

    public function write($data): bool
    {
        if (is_string($data)) {
            $this->writtenData[] = $data;
        }

        return true;
    }

    public function end($data = null): void
    {
        if ($data !== null) {
            $this->write($data);
        }

        $this->close();
    }

    /**
     * Simulate receiving data on this connection (triggers the 'data' event).
     */
    public function receive(string $data): void
    {
        $this->emit('data', [$data]);
    }

    /**
     * Send a payload frame to this connection using the daemon protocol.
     */
    public function sendPayload(string $type, string $data): void
    {
        $message = "v1:{$type}:{$data}";
        $frame = strlen($message) . ":{$message}";

        $this->receive($frame);
    }

    /**
     * @return array<int, string>
     */
    public function writtenData(): array
    {
        return $this->writtenData;
    }

    public function lastWritten(): ?string
    {
        if ($this->writtenData === []) {
            return null;
        }

        return $this->writtenData[count($this->writtenData) - 1];
    }

    public function assertWritten(string $expected): self
    {
        Assert::assertContains($expected, $this->writtenData, "Expected '{$expected}' to have been written to connection");

        return $this;
    }

    public function assertWrittenCount(int $expected): self
    {
        Assert::assertCount($expected, $this->writtenData, "Expected {$expected} writes, got " . count($this->writtenData));

        return $this;
    }

    public function assertLastWritten(string $expected): self
    {
        $last = $this->lastWritten();
        Assert::assertNotNull($last, 'No data has been written to the connection');
        Assert::assertSame($expected, $last, "Expected last write to be '{$expected}', got '{$last}'");

        return $this;
    }

    public function assertClosed(): self
    {
        Assert::assertTrue($this->closed, 'Expected connection to be closed');

        return $this;
    }

    public function assertOpen(): self
    {
        Assert::assertFalse($this->closed, 'Expected connection to be open');

        return $this;
    }
}

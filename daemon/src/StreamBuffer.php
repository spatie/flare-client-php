<?php

namespace Spatie\FlareDaemon;

class StreamBuffer
{
    private const FLUSH_THRESHOLD = 6 * 1024 * 1024; // ~6MB

    /** @var array<int, string> */
    private array $payloads = [];

    private int $size = 0;

    public function add(string $payload): void
    {
        $this->payloads[] = $payload;
        $this->size += strlen($payload);
    }

    public function shouldFlush(): bool
    {
        return $this->size >= self::FLUSH_THRESHOLD;
    }

    /**
     * @return array<int, string>
     */
    public function pull(): array
    {
        $payloads = $this->payloads;

        $this->payloads = [];
        $this->size = 0;

        return $payloads;
    }

    public function size(): int
    {
        return $this->size;
    }

    public function count(): int
    {
        return count($this->payloads);
    }

    public function isEmpty(): bool
    {
        return $this->payloads === [];
    }
}

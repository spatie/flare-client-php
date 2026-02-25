<?php

namespace Spatie\FlareDaemon;

class NullBuffer
{
    public function add(string $payload): void
    {
        // No-op: payload is dropped
    }

    public function shouldFlush(): bool
    {
        return false;
    }

    /**
     * @return array<int, string>
     */
    public function pull(): array
    {
        return [];
    }

    public function size(): int
    {
        return 0;
    }

    public function count(): int
    {
        return 0;
    }

    public function isEmpty(): bool
    {
        return true;
    }
}

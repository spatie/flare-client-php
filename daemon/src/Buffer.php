<?php

namespace Spatie\FlareDaemon;

use Spatie\FlareDaemon\Support\Json;

class Buffer
{
    /**
     * @var array<int, array{
     *     payload: array<array-key, mixed>,
     *     arrived_at: float,
     *     bytes: int
     * }>
     */
    protected array $items = [];

    protected int $bufferedBytes = 0;

    protected bool $flushing = false;

    public function __construct(
        public readonly string $apiKey,
        public readonly string $type,
        protected int $byteThreshold,
    ) {
    }

    /**
     * @param array<array-key, mixed> $payload
     */
    public function add(array $payload, float $arrivedAt): void
    {
        $bytes = strlen(Json::encode($payload));

        $this->items[] = [
            'payload' => $payload,
            'arrived_at' => $arrivedAt,
            'bytes' => $bytes,
        ];

        $this->bufferedBytes += $bytes;
    }

    /**
     * @return array{
     *     payload: array<array-key, mixed>,
     *     arrived_at: float,
     *     bytes: int
     * }|null
     */
    public function peek(): ?array
    {
        return $this->items[0] ?? null;
    }

    /**
     * @return array{
     *     payload: array<array-key, mixed>,
     *     arrived_at: float,
     *     bytes: int
     * }|null
     */
    public function shift(): ?array
    {
        $item = array_shift($this->items);

        if ($item !== null) {
            $this->bufferedBytes -= $item['bytes'];
        }

        return $item;
    }

    /**
     * @return array<int, array{
     *     payload: array<array-key, mixed>,
     *     arrived_at: float,
     *     bytes: int
     * }>
     */
    public function drain(): array
    {
        $items = $this->items;
        $this->items = [];
        $this->bufferedBytes = 0;

        return $items;
    }

    /** @phpstan-impure */
    public function hasItems(): bool
    {
        return $this->items !== [];
    }

    public function shouldFlushBySize(): bool
    {
        return $this->bufferedBytes >= $this->byteThreshold;
    }

    public function oldestAge(float $now): ?float
    {
        $oldest = $this->items[0] ?? null;

        return $oldest === null ? null : max(0.0, $now - $oldest['arrived_at']);
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function isFlushing(): bool
    {
        return $this->flushing;
    }

    public function markFlushing(bool $flushing): void
    {
        $this->flushing = $flushing;
    }
}

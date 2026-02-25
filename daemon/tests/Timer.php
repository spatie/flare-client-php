<?php

namespace Tests;

use PHPUnit\Framework\Assert;
use React\EventLoop\TimerInterface;

class Timer implements TimerInterface
{
    public bool $cancelled = false;

    public int $fireCount = 0;

    public function __construct(
        public float $interval,
        public \Closure $callback,
        public bool $periodic,
        public float $scheduledAt,
    ) {
    }

    public function getInterval(): float
    {
        return $this->interval;
    }

    public function getCallback(): \Closure
    {
        return $this->callback;
    }

    public function isPeriodic(): bool
    {
        return $this->periodic;
    }

    public function nextFireAt(): float
    {
        return $this->scheduledAt + $this->interval;
    }

    public function fire(): void
    {
        $this->fireCount++;
        ($this->callback)($this);

        if ($this->periodic && ! $this->cancelled) {
            $this->scheduledAt = $this->scheduledAt + $this->interval;
        } elseif (! $this->periodic) {
            $this->cancelled = true;
        }
    }

    public function cancel(): void
    {
        $this->cancelled = true;
    }

    public function assertFired(int $times = 1): self
    {
        Assert::assertSame($times, $this->fireCount, "Expected timer to have fired {$times} time(s), but fired {$this->fireCount} time(s)");

        return $this;
    }

    public function assertNotFired(): self
    {
        Assert::assertSame(0, $this->fireCount, "Expected timer to not have fired, but fired {$this->fireCount} time(s)");

        return $this;
    }

    public function assertCancelled(): self
    {
        Assert::assertTrue($this->cancelled, 'Expected timer to be cancelled');

        return $this;
    }

    public function assertNotCancelled(): self
    {
        Assert::assertFalse($this->cancelled, 'Expected timer to not be cancelled');

        return $this;
    }
}

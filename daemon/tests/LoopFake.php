<?php

namespace Tests;

use React\EventLoop\TimerInterface;

class LoopFake
{
    private float $currentTime = 1000000000.0;

    private bool $running = false;

    /** @var array<int, Timer> */
    private array $timers = [];

    /** @var array<int, callable> */
    private array $futureTicks = [];

    /** @var array<int, array<int, callable>> */
    private array $signals = [];

    private int $nextTimerId = 0;

    public function now(): float
    {
        return $this->currentTime;
    }

    public function running(): bool
    {
        return $this->running;
    }

    public function setRunning(bool $running): void
    {
        $this->running = $running;
    }

    public function get(): self
    {
        return $this;
    }

    public function run(): void
    {
        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function addTimer(float $interval, callable $callback): Timer
    {
        $timer = new Timer(
            interval: $interval,
            callback: \Closure::fromCallable($callback),
            periodic: false,
            scheduledAt: $this->currentTime,
        );

        $this->timers[$this->nextTimerId++] = $timer;

        return $timer;
    }

    public function addPeriodicTimer(float $interval, callable $callback): Timer
    {
        $timer = new Timer(
            interval: $interval,
            callback: \Closure::fromCallable($callback),
            periodic: true,
            scheduledAt: $this->currentTime,
        );

        $this->timers[$this->nextTimerId++] = $timer;

        return $timer;
    }

    public function cancelTimer(TimerInterface|Timer $timer): void
    {
        if ($timer instanceof Timer) {
            $timer->cancel();
        }
    }

    public function futureTick(callable $listener): void
    {
        $this->futureTicks[] = $listener;
    }

    public function addSignal(int $signal, callable $listener): void
    {
        $this->signals[$signal][] = $listener;
    }

    /**
     * @return array<int, array<int, callable>>
     */
    public function signals(): array
    {
        return $this->signals;
    }

    /**
     * Advance time by the given number of seconds, firing all timers that are due.
     */
    public function advance(float $seconds): void
    {
        $targetTime = $this->currentTime + $seconds;

        $this->processFutureTicks();

        while (true) {
            $nextTimer = $this->findNextTimer($targetTime);

            if ($nextTimer === null) {
                break;
            }

            $this->currentTime = $nextTimer->nextFireAt();
            $nextTimer->fire();

            $this->processFutureTicks();
        }

        $this->currentTime = $targetTime;

        $this->processFutureTicks();
    }

    /**
     * Process only future ticks without advancing time.
     */
    public function tick(): void
    {
        $this->processFutureTicks();
    }

    /**
     * @return array<int, Timer>
     */
    public function timers(): array
    {
        return array_values(array_filter(
            $this->timers,
            fn (Timer $timer) => ! $timer->cancelled,
        ));
    }

    /**
     * @return array<int, Timer>
     */
    public function allTimers(): array
    {
        return array_values($this->timers);
    }

    public function timerCount(): int
    {
        return count($this->timers());
    }

    /**
     * Find the next timer that will fire at or before the target time.
     */
    private function findNextTimer(float $targetTime): ?Timer
    {
        $earliest = null;
        $earliestFireAt = $targetTime + 1;

        foreach ($this->timers as $timer) {
            if ($timer->cancelled) {
                continue;
            }

            $fireAt = $timer->nextFireAt();

            if ($fireAt <= $targetTime && $fireAt < $earliestFireAt) {
                $earliest = $timer;
                $earliestFireAt = $fireAt;
            }
        }

        return $earliest;
    }

    private function processFutureTicks(): void
    {
        while ($this->futureTicks !== []) {
            $ticks = $this->futureTicks;
            $this->futureTicks = [];

            foreach ($ticks as $tick) {
                $tick();
            }
        }
    }
}

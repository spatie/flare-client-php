<?php

namespace Spatie\FlareDaemon;

use React\EventLoop\LoopInterface;

class Loop
{
    private bool $running = false;

    public function __construct(
        private LoopInterface $loop,
    ) {
    }

    public function get(): LoopInterface
    {
        return $this->loop;
    }

    public function running(): bool
    {
        return $this->running;
    }

    public function run(): void
    {
        $this->running = true;

        $this->loop->run();

        $this->running = false;
    }

    public function stop(): void
    {
        $this->running = false;

        $this->loop->stop();
    }

    public function addTimer(float $interval, callable $callback): void
    {
        $this->loop->addTimer($interval, $callback);
    }

    public function addPeriodicTimer(float $interval, callable $callback): void
    {
        $this->loop->addPeriodicTimer($interval, $callback);
    }

    public function futureTick(callable $listener): void
    {
        $this->loop->futureTick($listener);
    }

    public function addSignal(int $signal, callable $listener): void
    {
        $this->loop->addSignal($signal, $listener);
    }
}

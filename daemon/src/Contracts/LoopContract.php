<?php

namespace Spatie\FlareDaemon\Contracts;

use React\EventLoop\LoopInterface;

interface LoopContract
{
    public function get(): LoopInterface;

    public function running(): bool;

    public function run(): void;

    public function stop(): void;

    /** @return void */
    public function addTimer(float $interval, callable $callback);

    /** @return void */
    public function addPeriodicTimer(float $interval, callable $callback);

    public function futureTick(callable $listener): void;

    public function addSignal(int $signal, callable $listener): void;
}

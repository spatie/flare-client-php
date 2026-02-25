<?php

namespace Tests;

use Spatie\FlareDaemon\Contracts\Clock;

class SyncedClock implements Clock
{
    public function __construct(
        private LoopFake $loop,
    ) {
    }

    public function now(): float
    {
        return $this->loop->now();
    }
}

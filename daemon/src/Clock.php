<?php

namespace Spatie\FlareDaemon;

use Spatie\FlareDaemon\Contracts\Clock as ClockContract;

class Clock implements ClockContract
{
    public function now(): float
    {
        return microtime(true);
    }
}

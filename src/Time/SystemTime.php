<?php

namespace Spatie\FlareClient\Time;

class SystemTime implements Time
{
    public function getCurrentTime(): int
    {
        // We don't use hrtime here, because the nano time precision is not needed
        // Also it wouldn't be easy to use these times as real time to display
        // By default everything is in milliseconds

        return (int) (microtime(true) * 1000_000);
    }
}

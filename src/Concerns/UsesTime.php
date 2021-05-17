<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Time\SystemTime;
use Spatie\FlareClient\Time\Time;

trait UsesTime
{
    /** @var \Spatie\FlareClient\Time\Time */
    public static $time;

    public static function useTime(Time $time)
    {
        self::$time = $time;
    }

    public function getCurrentTime(): int
    {
        $time = self::$time ?? new SystemTime();

        return $time->getCurrentTime();
    }
}

<?php

namespace Spatie\FlareClient\Support;

use InvalidArgumentException;
use Spatie\FlareClient\Time\Time;

class TimeInterval
{
    /** @return array{0: int, 1:int} */
    public static function resolve(
        Time $time,
        ?int $start = null,
        ?int $end = null,
        ?int $duration = null,
    ): array {
        return match (true) {
            $start !== null && $end !== null => [$start, $end],
            $start !== null && $duration !== null => [$start, $start + $duration],
            $end !== null && $duration !== null => [$end - $duration, $end],
            $start === null && $end === null && $duration !== null => [$time->getCurrentTime() - $duration, $time->getCurrentTime()],
            default => throw new InvalidArgumentException('Span cannot be started, no valid timings provided'),
        };
    }
}

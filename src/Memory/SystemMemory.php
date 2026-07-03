<?php

namespace Spatie\FlareClient\Memory;

class SystemMemory implements Memory
{
    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    public function resetPeakMemoryUsage(): void
    {
        if (! self::phpVersionCanTrackMemory()) {
            return;
        }

        memory_reset_peak_usage();
    }

    public static function phpVersionCanTrackMemory(): bool
    {
        // memory_reset_peak_usage() only exists on PHP 8.2+, so peak memory cannot be scoped per span before then
        return PHP_VERSION_ID >= 80200;
    }
}

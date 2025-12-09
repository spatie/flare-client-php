<?php

namespace Spatie\FlareClient\Memory;

class SystemMemory implements Memory
{
    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage();
    }

    public function resetPeaMemoryUsage(): void
    {
        memory_reset_peak_usage();
    }
}

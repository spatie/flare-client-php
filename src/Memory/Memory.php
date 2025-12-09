<?php

namespace Spatie\FlareClient\Memory;

interface Memory
{
    public function getPeakMemoryUsage(): int;

    public function resetPeaMemoryUsage(): void;
}

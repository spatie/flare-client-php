<?php

namespace Spatie\FlareClient\Time;

interface Time
{
    // In microseconds
    public function getCurrentTime(): int;
}

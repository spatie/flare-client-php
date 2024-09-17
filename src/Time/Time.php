<?php

namespace Spatie\FlareClient\Time;

interface Time
{
    // In nano seconds
    public function getCurrentTime(): int;
}

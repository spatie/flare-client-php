<?php

namespace Spatie\FlareClient\Contracts;

interface Recorder
{
    public function start(): void;

    public function reset(): void;
}

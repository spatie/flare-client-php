<?php

namespace Spatie\FlareDaemon\Contracts;

interface Clock
{
    public function now(): float;
}

<?php

namespace Spatie\FlareClient\Contracts\Recorders;

use Spatie\FlareClient\Enums\RecorderType;

interface Recorder
{
    public static function type(): string|RecorderType;

    public function boot(): void;

    public function reset(): void;
}

<?php

namespace Spatie\FlareClient\Contracts;

use Psr\Container\ContainerInterface;
use Spatie\FlareClient\Enums\RecorderType;

interface Recorder
{
    public static function type(): string|RecorderType;

    public function configure(array $config): void;

    public function start(): void;

    public function reset(): void;
}

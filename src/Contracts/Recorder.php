<?php

namespace Spatie\FlareClient\Contracts;

use Psr\Container\ContainerInterface;

interface Recorder
{
    public static function initialize(ContainerInterface $container, array $config): static;

    public function start(): void;

    public function reset(): void;
}

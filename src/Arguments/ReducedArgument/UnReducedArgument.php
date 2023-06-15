<?php

namespace Spatie\FlareClient\Arguments\ReducedArgument;

class UnReducedArgument implements ReducedArgumentContract
{
    private static self|null $instance = null;

    private function __construct()
    {
    }

    public static function create(): self
    {
        return self::$instance ??= new self();
    }
}

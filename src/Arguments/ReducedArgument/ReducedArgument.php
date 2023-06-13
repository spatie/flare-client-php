<?php

namespace Spatie\FlareClient\Arguments\ReducedArgument;

use UnitEnum;

class ReducedArgument implements ReducedArgumentContract
{
    public function __construct(
        public string|array|int|float|bool|null|UnitEnum $value,
    ) {
    }
}

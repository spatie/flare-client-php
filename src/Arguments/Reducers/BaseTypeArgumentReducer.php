<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;

class BaseTypeArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (is_int($argument)
            || is_float($argument)
            || is_bool($argument)
            || is_string($argument)
            || $argument === null
        ) {
            return new ReducedArgument($argument);
        }

        return new UnReducedArgument();
    }
}

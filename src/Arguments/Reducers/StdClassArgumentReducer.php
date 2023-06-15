<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;
use stdClass;

class StdClassArgumentReducer extends ArrayArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (! $argument instanceof stdClass) {
            return UnReducedArgument::create();
        }

        return parent::reduceArgument((array) $argument, stdClass::class);
    }
}

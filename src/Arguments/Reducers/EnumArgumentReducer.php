<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;
use UnitEnum;

class EnumArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (! $argument instanceof UnitEnum) {
            return UnReducedArgument::create();
        }

        return new ReducedArgument(
            $argument::class.'::'.$argument->name,
            get_class($argument),
        );
    }
}

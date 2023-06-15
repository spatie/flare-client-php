<?php

namespace Spatie\FlareClient\Arguments;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;

class ReduceArgumentPayloadAction
{
    public function __construct(
        protected ArgumentReducers $argumentReducers
    ) {
    }

    public function reduce(mixed $argument, bool $includeObjectType = false): ReducedArgument
    {
        foreach ($this->argumentReducers->argumentReducers as $reducer) {
            $reduced = $reducer->execute($argument);

            if ($reduced instanceof ReducedArgument) {
                return $reduced;
            }
        }

        if (gettype($argument) === 'object' && $includeObjectType) {
            return new ReducedArgument(
                'object ('.get_class($argument).')',
                get_debug_type($argument),
            );
        }

        if (gettype($argument) === 'object') {
            return new ReducedArgument('object', get_debug_type($argument),);
        }

        return new ReducedArgument(
            $argument,
            get_debug_type($argument),
        );
    }
}

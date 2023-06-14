<?php

namespace Spatie\FlareClient\Arguments;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;

class ReduceArgumentPayloadAction
{
    public function __construct(
        protected ArgumentReducers $argumentReducers
    ) {
    }

    public function reduce(mixed $argument): ReducedArgument
    {
        foreach ($this->argumentReducers->argumentReducers as $reducer) {
            $reduced = $reducer->execute($argument);

            if ($reduced instanceof ReducedArgument) {
                return $reduced;
            }
        }

        $type = gettype($argument);

        return new ReducedArgument(
            $type === 'object' ? 'object ('.get_class($argument).')' : $argument,
            get_debug_type($argument),
        );
    }
}

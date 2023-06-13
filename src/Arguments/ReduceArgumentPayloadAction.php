<?php

namespace Spatie\FlareClient\Arguments;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;

class ReduceArgumentPayloadAction
{
    /**
     * @param array<\Spatie\FlareClient\Arguments\Reducers\ArgumentReducer> $argumentReducers
     */
    public function __construct(
        protected array $argumentReducers = []
    ) {
    }

    public function reduce(mixed $argument): ReducedArgument
    {
        foreach ($this->argumentReducers as $reducer) {
            $reduced = $reducer->execute($argument);

            if ($reduced instanceof ReducedArgument) {
                return $reduced;
            }
        }

        return new ReducedArgument(
            gettype($argument) === 'object'
                ? 'object ('.get_class($argument).')'
                : $argument
        );
    }
}

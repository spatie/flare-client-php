<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ArgumentReducers;
use Spatie\FlareClient\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\TruncatedReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;

class ArrayArgumentReducer implements ArgumentReducer
{
    protected int $maxArraySize = 25;

    protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction;

    public function __construct()
    {
        $this->reduceArgumentPayloadAction = new ReduceArgumentPayloadAction(ArgumentReducers::minimal());
    }

    public function execute(mixed $argument): ReducedArgument|UnReducedArgument
    {
        if (! is_array($argument)) {
            return new UnReducedArgument();
        }

        foreach ($argument as $key => $value) {
            $argument[$key] = $this->reduceArgumentPayloadAction->reduce($value)->value;
        }

        if (count($argument) > $this->maxArraySize) {
            return new TruncatedReducedArgument(
                array_slice($argument, 0, $this->maxArraySize),
                'array'
            );
        }

        return new ReducedArgument($argument, 'array');
    }
}

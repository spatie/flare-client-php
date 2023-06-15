<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ArgumentReducers;
use Spatie\FlareClient\Arguments\ReduceArgumentPayloadAction;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\TruncatedReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;

class ArrayArgumentReducer implements ReducedArgumentContract
{
    protected int $maxArraySize = 25;

    protected ReduceArgumentPayloadAction $reduceArgumentPayloadAction;

    public function __construct()
    {
        $this->reduceArgumentPayloadAction = new ReduceArgumentPayloadAction(ArgumentReducers::minimal());
    }

    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (! is_array($argument)) {
            return UnReducedArgument::create();
        }

        return $this->reduceArgument($argument, 'array');
    }

    protected function reduceArgument(array $argument, string $originalType): ReducedArgument|TruncatedReducedArgument
    {
        foreach ($argument as $key => $value) {
            $argument[$key] = $this->reduceArgumentPayloadAction->reduce(
                $value,
                includeObjectType: true
            )->value;
        }

        if (count($argument) > $this->maxArraySize) {
            return new TruncatedReducedArgument(
                array_slice($argument, 0, $this->maxArraySize),
                'array'
            );
        }

        return new ReducedArgument($argument, $originalType);
    }
}

<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use ReflectionFunction;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;

class ClosureArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (! $argument instanceof \Closure) {
            return UnReducedArgument::create();
        }

        $reflection = new ReflectionFunction($argument);

        if ($reflection->getFileName() && $reflection->getStartLine() && $reflection->getEndLine()) {
            return new ReducedArgument(
                "{$reflection->getFileName()}:{$reflection->getStartLine()}-{$reflection->getEndLine()}",
                'Closure'
            );
        }

        return new ReducedArgument("{$reflection->getFileName()}", 'Closure');
    }
}

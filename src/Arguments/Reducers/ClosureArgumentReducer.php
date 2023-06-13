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
            return new UnReducedArgument();
        }

        $reflection = new ReflectionFunction($argument);

        if ($reflection->getFileName() && $reflection->getStartLine() && $reflection->getEndLine()) {
            return new ReducedArgument(
                "{$reflection->name}({$reflection->getFileName()}:{$reflection->getStartLine()}-{$reflection->getEndLine()})"
            );
        }

        return new ReducedArgument("{$reflection->name}");
    }
}

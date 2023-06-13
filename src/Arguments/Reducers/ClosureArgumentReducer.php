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
        if (! is_callable($argument)) {
            return new UnReducedArgument();
        }

        // @todo since we have arguments, we can output them?

        $reflection = new ReflectionFunction($argument);

        if ($reflection->getFileName() && $reflection->getStartLine() && $reflection->getEndLine()) {
            return new ReducedArgument(
                "{$reflection->name}({$reflection->getFileName()}:{$reflection->getStartLine()}-{$reflection->getEndLine()})"
            );
        }

        return new ReducedArgument("{$reflection->name}");
    }
}

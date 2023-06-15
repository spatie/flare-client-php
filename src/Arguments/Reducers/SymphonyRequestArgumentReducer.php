<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;
use Symfony\Component\HttpFoundation\Request;

class SymphonyRequestArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if(! $argument instanceof Request) {
            return new UnReducedArgument();
        }

        return new ReducedArgument(
            "{$argument->getMethod()}|{$argument->getUri()}",
            get_class($argument),
        );
    }
}

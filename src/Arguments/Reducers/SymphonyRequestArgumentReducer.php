<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;
use Spatie\FlareClient\Arguments\Reducers\ArgumentReducer;
use Symfony\Component\HttpFoundation\Request;
use UnitEnum;

class SymphonyRequestArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if(! $argument instanceof Request){
            return new UnReducedArgument();
        }

        return new ReducedArgument("{$argument->getMethod()}|{$argument->getUri()} (" . $argument::class . ')');
    }
}

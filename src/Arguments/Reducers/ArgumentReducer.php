<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;

interface ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract;
}

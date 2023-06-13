<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use UnitEnum;

interface ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract;
}

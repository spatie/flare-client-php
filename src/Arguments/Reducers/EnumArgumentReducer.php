<?php

namespace Spatie\FlareClient\Arguments\Reducers;

use IntBackedEnum;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgumentContract;
use Spatie\FlareClient\Arguments\ReducedArgument\ReducedArgument;
use Spatie\FlareClient\Arguments\ReducedArgument\UnReducedArgument;
use Spatie\FlareClient\Arguments\Reducers\ArgumentReducer;
use StringBackedEnum;
use UnitEnum;
use function _PHPStan_1f608dc6a\React\Promise\reduce;

class EnumArgumentReducer implements ArgumentReducer
{
    public function execute(mixed $argument): ReducedArgumentContract
    {
        if (! $argument instanceof UnitEnum) {
            return new UnReducedArgument();
        }

        return new ReducedArgument($argument::class.'::'.$argument->name);
    }
}

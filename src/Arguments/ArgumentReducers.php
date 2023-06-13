<?php

namespace Spatie\FlareClient\Arguments;

use Spatie\FlareClient\Arguments\Reducers\ArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\ArrayArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\BaseTypeArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\ClosureArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\DateTimeArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\DateTimeZoneArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\EnumArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\SymphonyRequestArgumentReducer;

class ArgumentReducers
{
    public static function create(array $argumentReducers): self
    {
        return new self(array_map(
            fn (string|ArgumentReducer $argumentReducer) => is_string($argumentReducer) ? new $argumentReducer() : $argumentReducer,
            $argumentReducers
        ));
    }

    public static function default(): self
    {
        return new self([
            new BaseTypeArgumentReducer(),
            new ArrayArgumentReducer(),
            new EnumArgumentReducer(),
            new ClosureArgumentReducer(),
            new DateTimeArgumentReducer(),
            new DateTimeZoneArgumentReducer(),
            new SymphonyRequestArgumentReducer(),
        ]);
    }

    /**
     * @param array<\Spatie\FlareClient\Arguments\Reducers\ArgumentReducer> $argumentReducers
     */
    protected function __construct(
        public array $argumentReducers = [],
    ) {
    }
}

<?php

namespace Spatie\FlareClient\Arguments;

use Spatie\FlareClient\Arguments\Reducers\ArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\ArrayArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\BaseTypeArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\ClosureArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\DateTimeArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\DateTimeZoneArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\EnumArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\MinimalArrayArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\StdClassArgumentReducer;
use Spatie\FlareClient\Arguments\Reducers\SymphonyRequestArgumentReducer;

class ArgumentReducers
{
    /**
     * @param array<ArgumentReducer|class-string<ArgumentReducer>> $argumentReducers
     */
    public static function create(array $argumentReducers): self
    {
        return new self(array_map(
            fn (string|ArgumentReducer $argumentReducer) => $argumentReducer instanceof ArgumentReducer ? $argumentReducer : new $argumentReducer(),
            $argumentReducers
        ));
    }

    public static function default(): self
    {
        return new self([
            new BaseTypeArgumentReducer(),
            new ArrayArgumentReducer(),
            new StdClassArgumentReducer(),
            new EnumArgumentReducer(),
            new ClosureArgumentReducer(),
            new DateTimeArgumentReducer(),
            new DateTimeZoneArgumentReducer(),
            new SymphonyRequestArgumentReducer(),
        ]);
    }

    public static function minimal(): self
    {
        return new self([
            new BaseTypeArgumentReducer(),
            new MinimalArrayArgumentReducer(),
            new EnumArgumentReducer(),
            new ClosureArgumentReducer(),
        ]);
    }

    /**
     * @param array<ArgumentReducer> $argumentReducers
     */
    protected function __construct(
        public array $argumentReducers = [],
    ) {
    }
}

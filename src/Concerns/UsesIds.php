<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Support\Ids;

trait UsesIds
{
    public static Ids $ids;

    public static function useIds(Ids $ids): void
    {
        self::$ids = $ids;
    }

    protected static function ids(): Ids
    {
        return self::$ids ??= new Ids();
    }
}

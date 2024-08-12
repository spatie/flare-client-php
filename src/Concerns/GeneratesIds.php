<?php

namespace Spatie\FlareClient\Concerns;

use Spatie\FlareClient\Support\IdsGenerator;

trait GeneratesIds
{
    public static IdsGenerator $idsGenerator;

    public static function useIdsGenerator(IdsGenerator $idsGenerator): void
    {
        self::$idsGenerator = $idsGenerator;
    }

    protected static function generateIdFor(): IdsGenerator
    {
        return self::$idsGenerator ??= new IdsGenerator();
    }
}

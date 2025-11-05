<?php

namespace Spatie\FlareClient\Support;

use Composer\InstalledVersions;

class Telemetry
{
    public const NAME = 'spatie/flare-client-php';

    public static function getName(): string
    {
        return self::NAME;
    }

    public static function getVersion(): string
    {
        if (! class_exists(InstalledVersions::class)) {
            return 'unknown';
        }

        return InstalledVersions::getVersion(static::NAME) ?? 'unknown';
    }
}

<?php

namespace Spatie\FlareClient\Support;

class Runtime
{
    public static function runningInConsole(): bool
    {
        if (isset($_ENV['APP_RUNNING_IN_CONSOLE'])) {
            return $_ENV['APP_RUNNING_IN_CONSOLE'] === 'true';
        }

        if (isset($_ENV['FLARE_FAKE_WEB_REQUEST'])) {
            return false;
        }

        return in_array(php_sapi_name(), ['cli', 'phpdb']);
    }
}

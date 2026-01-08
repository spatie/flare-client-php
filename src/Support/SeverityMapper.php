<?php

namespace Spatie\FlareClient\Support;

use Monolog\Level;

class SeverityMapper
{
    public static function fromSyslog(string $level): int
    {
        return match (strtoupper($level)) {
            'DEBUG' => 5, // DEBUG 1
            'INFO', 'INFORMAL' => 9, // INFO 1
            'NOTICE' => 10, // INFO 2
            'WARN', 'WARNING' => 13, // WARN 1
            'ERROR' => 17, // ERROR 1
            'CRITICAL' => 18, // ERROR 2
            'ALERT' => 19, // ERROR 3
            'EMERGENCY' => 21, // FATAL 1
            default => 9, // INFO 1
        };
    }

    public static function fromMonolog(Level $level): int
    {
        return self::fromSyslog($level->getName());
    }
}

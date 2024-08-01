<?php

namespace Spatie\FlareClient\Enums;

use Spatie\FlareClient\Contracts\FlareSpanEventType;

enum SpanEventType: string implements FlareSpanEventType
{
    case Log = 'php_log';
    case Glow = 'php_glow';
    case Dump = 'php_dump';
    case Exception = 'php_exception';

    case CacheHit = 'php_cache_hit';
    case CacheMiss = 'php_cache_miss';
    case CacheKeyWritten = 'php_cache_key_written';
    case CacheKeyForgotten = 'php_cache_key_forgotten';

    public function humanReadable(): string
    {
        return match ($this) {
            self::Log => 'log',
            self::Glow => 'glow',
            self::Dump => 'dump',
            self::Exception => 'exception',
            self::CacheHit => 'cache hit',
            self::CacheMiss => 'cache miss',
            self::CacheKeyWritten => 'cache key written',
            self::CacheKeyForgotten => 'cache key forgotten',
        };
    }
}

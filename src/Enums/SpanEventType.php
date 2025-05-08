<?php

namespace Spatie\FlareClient\Enums;

use Spatie\FlareClient\Contracts\FlareSpanEventType;

enum SpanEventType: string implements FlareSpanEventType
{
    case Log = 'php_log';
    case Glow = 'php_glow';
    case Dump = 'php_dump';
    case Exception = 'php_exception';
    case Cache = 'php_cache';
    case Custom = 'custom';
}

<?php

namespace Spatie\FlareClient\Performance\Enums;

use Spatie\FlareClient\Contracts\FlareSpanEventType;

enum SpanEventType: string implements FlareSpanEventType
{
    case Log = 'php_log';
}

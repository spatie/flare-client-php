<?php

namespace Spatie\FlareClient\Performance\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Request = 'php_request';
    case Query = 'php_query';
}

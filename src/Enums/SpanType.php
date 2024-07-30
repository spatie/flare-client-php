<?php

namespace Spatie\FlareClient\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Request = 'php_request';
    case Query = 'php_query';

    case Transaction = 'php_transaction';
}

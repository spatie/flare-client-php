<?php

namespace Spatie\FlareClient\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Request = 'php_request';
    case Query = 'php_query';
    case Transaction = 'php_transaction';
    case Command = 'php_command';

    public function humanReadable(): string
    {
        return match ($this) {
            self::Request => 'request',
            self::Query => 'query',
            self::Transaction => 'transaction',
            self::Command => 'command',
        };
    }
}

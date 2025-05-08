<?php

namespace Spatie\FlareClient\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Request = 'php_request';
    case Query = 'php_query';
    case RedisCommand = 'redis_command';
    case Transaction = 'php_transaction';
    case Command = 'php_command';
    case View = 'php_view';
    case HttpRequest = 'php_http_request';
    case Filesystem = 'php_filesystem';

    case Application = 'php_application';
    case ApplicationRegistration = 'php_application_registration';
    case ApplicationBoot = 'php_application_boot';
    case ApplicationTerminating = 'php_application_terminating';

    case Custom = 'custom';
}

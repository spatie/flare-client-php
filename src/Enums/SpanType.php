<?php

namespace Spatie\FlareClient\Enums;

use Spatie\FlareClient\Contracts\FlareSpanType;

enum SpanType: string implements FlareSpanType
{
    case Request = 'php_request';
    case Response = 'php_response';
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

    case Routing = 'php_routing';
    case GlobalBeforeMiddleware = 'php_global_before_middleware';
    case BeforeMiddleware = 'php_before_middleware';
    case AfterMiddleware = 'php_after_middleware';
    case GlobalAfterMiddleware = 'php_global_after_middleware';

    case Custom = 'custom';
}

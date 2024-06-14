<?php

namespace Spatie\FlareClient\Performance\Enums;

enum SpanType: string
{
    case PhpRequest = 'php_request';
    case PhpQuery = 'php_query';
}

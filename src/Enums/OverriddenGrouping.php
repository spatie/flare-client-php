<?php

namespace Spatie\FlareClient\Enums;

enum OverriddenGrouping: string
{
    case ExceptionClass = 'exception_class';
    case ExceptionMessage = 'exception_message';
    case ExceptionMessageAndClass = 'exception_message_and_class';
}

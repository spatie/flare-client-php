<?php

namespace Spatie\FlareClient\Enums;

enum SpanStatusCode: int
{
    case Unset = 0;
    case Ok = 1; // Should only be manually set by the developer
    case Error = 2;
}

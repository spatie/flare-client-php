<?php

namespace Spatie\FlareClient\Enums;

enum FlareEntityType: string
{
    case Logs = 'logs';
    case Traces = 'traces';
    case Errors = 'errors';
}

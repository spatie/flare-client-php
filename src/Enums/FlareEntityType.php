<?php

namespace Spatie\FlareClient\Enums;

enum FlareEntityType: string
{
    case Logs = 'logs';
    case Traces = 'traces';
    case Errors = 'errors';

    public function singleName(): string
    {
        return match ($this) {
            FlareEntityType::Logs => 'log',
            FlareEntityType::Traces => 'trace',
            FlareEntityType::Errors => 'error',
        };
    }
}

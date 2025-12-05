<?php

namespace Spatie\FlareClient\Enums;

// TODO: merge with kind?
enum FlarePayloadType: string
{
    case Error = 'error';
    case TestError = 'testError';
    case Traces = 'traces';
    case Logs = 'logs';
}

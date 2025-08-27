<?php

namespace Spatie\FlareClient\Enums;

enum FlarePayloadType: string
{
    case Error = 'error';
    case TestError = 'testError';
    case Traces = 'traces';
}

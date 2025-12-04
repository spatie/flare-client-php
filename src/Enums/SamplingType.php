<?php

namespace Spatie\FlareClient\Enums;

enum SamplingType
{
    case Sampling; // We're currently sampling
    case Waiting; // We're waiting for the next opportunity to do the sample lottery
    case Disabled; // Sampling is disabled by config
}

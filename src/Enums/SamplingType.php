<?php

namespace Spatie\FlareClient\Enums;

enum SamplingType
{
    case Off; // When we decided not to sample
    case Sampling; // When we're currently waiting to sample
    case Waiting; // We're waiting for the next opportunity to sample
    case Disabled; // Sampling is disabled
}

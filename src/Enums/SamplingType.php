<?php

namespace Spatie\FlareClient\Enums;

enum SamplingType
{
    case Off; // When we decided not to sample this run
    case Sampling; // We're currently sampling
    case Waiting; // We're waiting for the next opportunity to do the sample lottery
    case Disabled; // Sampling is disabled by config
}

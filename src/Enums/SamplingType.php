<?php

namespace Spatie\FlareClient\Enums;

enum SamplingType
{
    case Off; // When we decided not to sample
    case Sampling; // When we decided to sample
    case Waiting; // We're waiting for the next opportunity to sample
}

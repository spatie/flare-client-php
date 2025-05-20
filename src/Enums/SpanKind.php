<?php

namespace Spatie\FlareClient\Enums;

enum SpanKind: int
{
    case Unspecified = 0;
    case Internal = 1;
    case Server = 2;
    case Client = 3;
    case Producer = 4;
    case Consumer = 5;
}

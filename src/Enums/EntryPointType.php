<?php

namespace Spatie\FlareClient\Enums;

enum EntryPointType: string
{
    case Web = 'web';
    case Cli = 'cli';
    case Queue = 'queue';
}

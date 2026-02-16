<?php

namespace Spatie\FlareClient\Enums;

enum ResourceIncludeType: string
{
    case Base = 'Base';
    case ComposerPackages = 'ComposerPackages';
    case Host = 'Host';
    case OperatingSystem = 'OperatingSystem';
    case Process = 'Process';
    case ProcessRuntime = 'ProcessRuntime';
    case Git = 'Git';
    case CustomAttributes = 'CustomAttributes';
}

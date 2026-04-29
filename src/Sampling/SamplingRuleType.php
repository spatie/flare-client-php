<?php

namespace Spatie\FlareClient\Sampling;

use Spatie\FlareClient\Enums\EntryPointType;

enum SamplingRuleType: string
{
    case Url = 'url';
    case Route = 'route';
    case Command = 'command';
    case Job = 'job';
    case Closure = 'closure';
    case EarlyClosure = 'early_closure';

    public function appliesTo(EntryPointType $entryPointType): bool
    {
        return match ($this) {
            self::Url, self::Route => $entryPointType === EntryPointType::Web,
            self::Command => $entryPointType === EntryPointType::Cli,
            self::Job => $entryPointType === EntryPointType::Queue,
            self::Closure, self::EarlyClosure => true,
        };
    }
}

<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Enums\EntryPointType;

class ConsoleAttributesProvider
{
    public function toArray(array $arguments): array
    {
        return [
            'process.command_args' => $arguments,
            'flare.entry_point.type' => EntryPointType::Cli,
            'flare.entry_point.value' => implode(' ', $arguments),
            'flare.entry_point.class' => null,
        ];
    }
}

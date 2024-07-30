<?php

namespace Spatie\FlareClient\AttributesProviders;

class ConsoleAttributesProvider
{
    public function toArray(array $arguments): array
    {
        return [
            'process.command_args' => $arguments,
        ];
    }
}

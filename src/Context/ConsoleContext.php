<?php

namespace Spatie\FlareClient\Context;

class ConsoleContext implements ContextInterface
{
    protected array $arguments = [];

    public function __construct(array $arguments = [])
    {
        $this->arguments = $arguments;
    }

    public function toArray(): array
    {
        return [
            'arguments' => $this->arguments,
        ];
    }
}

<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\CommandAttributesProvider;

class PhpCommandAttributesProvider implements CommandAttributesProvider
{
    /** @param array<int, string>|null $arguments */
    public function __construct(
        protected string $command,
        protected ?string $commandClass = null,
        protected ?array $arguments = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'process.command_args' => $this->arguments ?? $_SERVER['argv'] ?? [],
        ];
    }

    public function command(): string
    {
        return $this->command;
    }

    public function commandClass(): ?string
    {
        return $this->commandClass;
    }
}

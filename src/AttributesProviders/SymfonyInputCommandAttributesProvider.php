<?php

namespace Spatie\FlareClient\AttributesProviders;

use Spatie\FlareClient\Contracts\CommandAttributesProvider;
use Symfony\Component\Console\Input\InputInterface;

class SymfonyInputCommandAttributesProvider implements CommandAttributesProvider
{
    public function __construct(
        protected InputInterface $input,
        protected string $command,
        protected ?string $commandClass = null,
    ) {
    }

    public function toArray(): array
    {
        return [
            'process.command_args' => $this->getArguments(),
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

    /** @return array<int, string> */
    protected function getArguments(): array
    {
        $arguments = array_map(
            fn ($argument) => is_array($argument) ? implode(',', $argument) : (string) $argument,
            array_values(array_filter($this->input->getArguments())),
        );

        $options = [];

        foreach ($this->input->getOptions() as $key => $option) {
            if ($option === null || $option === false) {
                continue;
            }

            if (is_bool($option) && $option === true) {
                $options[] = "--{$key}";

                continue;
            }

            if (is_array($option)) {
                $option = implode(',', $option);
            }

            $options[] = "--{$key}={$option}";
        }

        return array_merge($arguments, $options);
    }

    public function entryPointHandlerName(): ?string
    {
        return $this->commandClass;
    }

    public function entryPointHandlerType(): ?string
    {
        return 'symfony_command';
    }

    public function entryPointHandlerIdentifier(): ?string
    {
        return $this->command;
    }
}

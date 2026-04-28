<?php

namespace Spatie\FlareClient\Recorders\CommandRecorder;

use Closure;
use Psr\Container\ContainerInterface;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Symfony\Component\Console\Input\InputInterface;

class CommandRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Command;
    }

    public static function register(ContainerInterface $container, array $config): Closure
    {
        return fn () => new static(
            $container->get(Tracer::class),
            $container->get(BackTracer::class),
            $container->get(EntryPointResolver::class),
            $config,
        );
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected EntryPointResolver $entryPointResolver,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    public function recordStart(
        string $command,
        array|InputInterface $arguments,
        ?string $commandClass = null,
        ?string $entryPointHandlerType = 'php_command',
        array $attributes = []
    ): ?Span {
        $entryPoint = $this->entryPointResolver->get();

        if (! $entryPoint->handlerResolved) {
            $entryPoint->setHandler(
                handlerIdentifier: $command,
                handlerName: $commandClass,
                handlerType: $entryPointHandlerType,
            );
        }

        if ($this->shouldIgnoreCommand($command, $commandClass)) {
            $this->tracer->unsample();

            return null;
        }

        return $this->startSpan(
            name: "Command - {$command}",
            attributes: function () use ($attributes, $arguments, $command) {
                if ($arguments instanceof InputInterface) {
                    $arguments = $this->getArguments($arguments);
                }

                return [
                    'flare.span_type' => SpanType::Command,
                    'process.command' => $command,
                    'process.command_args' => $arguments,
                    ...$this->entryPointResolver->get()->toAttributes(),
                    ...$attributes,
                ];
            },
        );
    }

    public function shouldIgnoreCommand(?string $command, ?string $commandClass = null): bool
    {
        if ($command !== null && in_array($command, $this->defaultIgnoredCommands())) {
            return true;
        }

        if ($commandClass !== null && in_array($commandClass, $this->defaultIgnoredCommandClasses())) {
            return true;
        }

        return false;
    }

    public function recordEnd(
        int $exitCode = 0,
        array $attributes = []
    ): ?Span {
        return $this->endSpan(additionalAttributes: [
            'process.exit_code' => $exitCode,
            ...$attributes,
        ], includeMemoryUsage: true);
    }

    protected function getArguments(?InputInterface $input): array
    {
        if ($input === null) {
            return [];
        }

        $arguments = array_values(array_filter($input->getArguments()));

        $options = [];

        foreach ($input->getOptions() as $key => $option) {
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

    /** @return array<int, string> */
    protected function defaultIgnoredCommands(): array
    {
        return [];
    }

    /** @return array<int, class-string> */
    protected function defaultIgnoredCommandClasses(): array
    {
        return [];
    }
}

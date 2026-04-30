<?php

namespace Spatie\FlareClient\Recorders\CommandRecorder;

use Spatie\FlareClient\AttributesProviders\PhpConsoleAttributesProvider;
use Spatie\FlareClient\AttributesProviders\SymfonyInputCommandAttributesProvider;
use Spatie\FlareClient\Concerns\Recorders\PausableRecorder;
use Spatie\FlareClient\Contracts\CommandAttributesProvider;
use Spatie\FlareClient\Contracts\EntryPointHandlerProvider;
use Spatie\FlareClient\EntryPoint\EntryPointResolver;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Support\PatternMatcher;
use Spatie\FlareClient\Tracer;
use Symfony\Component\Console\Input\InputInterface;

class CommandRecorder extends SpansRecorder
{
    use PausableRecorder;

    /** @var array<int, string> */
    protected array $ignoredCommands = [];

    /** @var array<int, string> */
    protected array $ignoredClasses = [];

    public static function type(): string|RecorderType
    {
        return RecorderType::Command;
    }

    public function __construct(
        Tracer $tracer,
        BackTracer $backTracer,
        protected EntryPointResolver $entryPointResolver,
        array $config,
    ) {
        parent::__construct($tracer, $backTracer, $config);
    }

    protected function configure(array $config): void
    {
        $this->ignoredCommands = $config['ignored_commands'] ?? [];
        $this->ignoredClasses = $config['ignored_classes'] ?? [];
    }

    public function recordStart(
        CommandAttributesProvider $commandAttributesProvider,
        array $attributes = []
    ): ?Span {
        $command = $commandAttributesProvider->command();
        $commandClass = $commandAttributesProvider->commandClass();

        $entryPoint = $this->entryPointResolver->get();

        if (! $entryPoint->handlerResolved) {
            $entryPointProvider = $commandAttributesProvider instanceof EntryPointHandlerProvider
                ? $commandAttributesProvider
                : null;

            $entryPoint->setHandler(
                handlerIdentifier: $entryPointProvider?->entryPointHandlerIdentifier() ?? $command,
                handlerName: $entryPointProvider?->entryPointHandlerName() ?? $commandClass,
                handlerType: $entryPointProvider?->entryPointHandlerType() ?? 'php_command',
            );
        }

        $this->tracer->reevaluateSampling();

        $shouldIgnore = $this->shouldIgnoreCommand($command, $commandClass);

        if ($shouldIgnore && empty($this->stack)) {
            $this->tracer->unsample();

            return null;
        }

        if ($shouldIgnore) {
            $this->pauseTrace();

            return null;
        }

        return $this->startSpan(
            name: "Command - {$command}",
            attributes: fn () => [
                'flare.span_type' => SpanType::Command,
                'process.command' => $command,
                ...$this->entryPointResolver->get()->toAttributes(),
                ...$commandAttributesProvider->toArray(),
                ...$attributes,
            ],
        );
    }

    /** @param array<int, string> $arguments */
    public function recordStartFromArguments(
        string $command,
        array $arguments,
        ?string $commandClass = null,
        array $attributes = []
    ): ?Span {
        return $this->recordStart(
            new PhpConsoleAttributesProvider($command, $commandClass, $arguments),
            $attributes,
        );
    }

    public function recordStartFromCliArguments(
        string $command,
        ?string $commandClass = null,
        array $attributes = []
    ): ?Span {
        return $this->recordStart(
            new PhpConsoleAttributesProvider($command, $commandClass),
            $attributes,
        );
    }

    public function recordStartFromSymfonyInput(
        string $command,
        InputInterface $input,
        ?string $commandClass = null,
        array $attributes = []
    ): ?Span {
        return $this->recordStart(
            new SymfonyInputCommandAttributesProvider($input, $command, $commandClass),
            $attributes,
        );
    }

    protected function shouldIgnoreCommand(string $command, ?string $commandClass = null): bool
    {
        $ignoredCommands = [...$this->ignoredCommands, ...$this->defaultIgnoredCommands()];
        $ignoredClasses = [...$this->ignoredClasses, ...$this->defaultIgnoredCommandClasses()];

        if (PatternMatcher::matchesAny($command, $ignoredCommands)) {
            return true;
        }

        if ($commandClass !== null && PatternMatcher::matchesAny($commandClass, $ignoredClasses)) {
            return true;
        }

        return false;
    }

    public function recordEnd(
        int $exitCode = 0,
        array $attributes = []
    ): ?Span {
        if ($this->pausedTrace()) {
            $this->resumeTrace();

            return null;
        }

        return $this->endSpan(additionalAttributes: [
            'process.exit_code' => $exitCode,
            ...$attributes,
        ], includeMemoryUsage: true);
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

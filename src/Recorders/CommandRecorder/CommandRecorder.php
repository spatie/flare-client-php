<?php

namespace Spatie\FlareClient\Recorders\CommandRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\Recorder;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Symfony\Component\Console\Input\InputInterface;

class CommandRecorder extends Recorder implements SpansRecorder
{
    /** @use RecordsSpans<Span> */
    use RecordsSpans;

    public static function type(): string|RecorderType
    {
        return RecorderType::Command;
    }

    public function __construct(
        protected Tracer $tracer,
        protected BackTracer $backTracer,
        array $config
    ) {
        $this->configure($config);
    }

    protected function canStartTraces(): bool
    {
        return true;
    }

    public function recordStart(
        string $command,
        array|InputInterface $arguments,
        ?string $entryPointClass = null,
        array $attributes = []
    ): ?Span {
        return $this->startSpan(
            name: "Command - {$command}",
            attributes: function () use ($entryPointClass, $attributes, $arguments, $command) {
                if ($arguments instanceof InputInterface) {
                    $arguments = $this->getArguments($arguments);
                }

                if ($entryPointClass !== null) {
                    $attributes['flare.entry_point_class'] = $entryPointClass;
                }

                return [
                    'flare.span_type' => SpanType::Command,
                    'process.command' => $command,
                    'process.command_args' => $arguments,
                    ...$attributes,
                ];
            },
        );
    }

    public function recordEnd(
        int $exitCode = 0,
        array $attributes = []
    ): ?Span {
        return $this->endSpan(additionalAttributes: [
            'process.exit_code' => $exitCode,
            ...$attributes,
        ]);
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
}

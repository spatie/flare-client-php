<?php

namespace Spatie\FlareClient\Recorders\CommandRecorder;

use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Recorders\SpansRecorder;
use Spatie\FlareClient\Spans\Span;
use Symfony\Component\Console\Input\InputInterface;

class CommandRecorder extends SpansRecorder
{
    public static function type(): string|RecorderType
    {
        return RecorderType::Command;
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
            canStartTrace: true,
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

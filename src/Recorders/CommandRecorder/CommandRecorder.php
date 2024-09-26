<?php

namespace Spatie\FlareClient\Recorders\CommandRecorder;

use Spatie\FlareClient\Concerns\Recorders\RecordsPendingSpans;
use Spatie\FlareClient\Contracts\Recorders\SpansRecorder;
use Spatie\FlareClient\Enums\RecorderType;
use Spatie\FlareClient\Enums\SpanType;
use Spatie\FlareClient\Spans\Span;
use Spatie\FlareClient\Support\BackTracer;
use Spatie\FlareClient\Tracer;
use Symfony\Component\Console\Input\InputInterface;

class CommandRecorder implements SpansRecorder
{
    /** @use RecordsPendingSpans<Span> */
    use RecordsPendingSpans;

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
        array $attributes = []
    ): ?Span {
        return $this->startSpan(function () use ($attributes, $arguments, $command) {
            if ($arguments instanceof InputInterface) {
                $arguments = $this->getArguments($arguments);
            }

            return Span::build(
                $this->tracer->currentTraceId(),
                $this->tracer->currentSpanId(),
                name: "Command - {$command}",
                attributes: [
                    'flare.span_type' => SpanType::Command,
                    'process.command' => $command,
                    'process.command_args' => $arguments,
                    ...$attributes,
                ]
            );
        });
    }

    public function recordEnd(
        int $exitCode = 0,
        array $attributes = []
    ): ?Span {
        return $this->endSpan(function (Span $span) use ($exitCode, $attributes) {
            $span->addAttribute('process.exit_code', $exitCode);

            if (! empty($attributes)) {
                $span->addAttributes($attributes);
            }
        });
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
            }

            if (is_array($option)) {
                $option = implode(',', $option);
            }

            $options[] = "--{$key}={$option}";
        }

        return array_merge($arguments, $options);
    }
}
